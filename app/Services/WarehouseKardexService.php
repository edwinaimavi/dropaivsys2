<?php

namespace App\Services;

use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItem;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpenseDistribution;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseKardexRecalculation;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseKardexService
{
    private const STATUS_REGISTERED = 'registered';

    private const STATUS_REVERSED = 'reversed';

    public function generateMovementNumber(): string
    {
        $lastNumber = WarehouseKardexMovement::query()
            ->where('movement_number', 'like', 'KDX-%')
            ->pluck('movement_number')
            ->map(fn (?string $number) => preg_match('/^KDX-(\d{6,})$/', (string) $number, $matches)
                    ? (int) $matches[1]
                    : 0)
            ->max() ?? 0;

        do {
            $lastNumber++;
            $movementNumber = 'KDX-'.str_pad((string) $lastNumber, 6, '0', STR_PAD_LEFT);
        } while (WarehouseKardexMovement::query()->where('movement_number', $movementNumber)->exists());

        return $movementNumber;
    }

    public function buildStockKey(int $warehouseId, int $articleId, ?string $lotNumber, mixed $expirationDate): string
    {
        $lot = trim((string) $lotNumber);
        $date = $this->formatDate($expirationDate);

        return implode('|', [
            $warehouseId,
            $articleId,
            $lot === '' ? 'SIN_LOTE' : mb_strtoupper($lot, 'UTF-8'),
            $date ?: 'SIN_FECHA',
        ]);
    }

    public function registerEntryFromWarehouseEntry(WarehouseEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $entry->loadMissing([
                'supplier',
                'currency',
                'items.article',
                'items.unit',
                'items.presentation',
                'items.brand',
                'items.lots',
                'expenses.distributions.item.lots',
            ]);

            if (! $entry->warehouse_id) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Seleccione un almacen para generar Kardex.',
                ]);
            }

            foreach ($entry->items as $item) {
                $this->registerEntryItem($entry, $item);
            }

            $this->syncLinkedCosts($entry);
        });
    }

    public function rebuildEntryMovements(WarehouseEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $this->reverseWarehouseEntry($entry, 'Reversion por edicion de ingreso de almacen');
            $this->registerEntryFromWarehouseEntry($entry->fresh([
                'supplier',
                'currency',
                'items.article',
                'items.unit',
                'items.presentation',
                'items.brand',
                'items.lots',
            ]));
        });
    }

    public function reverseWarehouseEntry(WarehouseEntry $entry, ?string $reason = null): void
    {
        DB::transaction(function () use ($entry, $reason) {
            $movements = WarehouseKardexMovement::query()
                ->where('source_type', WarehouseEntry::class)
                ->where('source_id', $entry->id)
                ->whereIn('operation_type', ['warehouse_entry', 'warehouse_entry_linked_cost'])
                ->where('status', self::STATUS_REGISTERED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($movements as $movement) {
                $this->reverseEntryMovement($movement, $entry, $reason);
            }
        });
    }

    public function registerExitFromElectronicInvoice(ElectronicInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = ElectronicInvoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->status !== 'generated' || $invoice->is_voided || $invoice->stock_moved_at) {
                return;
            }

            $invoice->loadMissing(['customer', 'warehouseEntry', 'items.article.category']);
            $stockItems = $invoice->items->filter(function (ElectronicInvoiceItem $item) {
                $categoryType = mb_strtoupper((string) $item->article?->category?->type, 'UTF-8');

                return $item->article_id && $categoryType !== 'SERVICIO';
            });

            if ($stockItems->isEmpty()) {
                return;
            }

            if (! ($invoice->warehouse_id ?: $invoice->warehouseEntry?->warehouse_id)) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Seleccione el almacén de salida.',
                ]);
            }

            foreach ($stockItems as $item) {
                $this->registerElectronicInvoiceItemExit($invoice, $item);
            }

            $invoice->update([
                'stock_moved_at' => now(),
                'stock_reversed_at' => null,
                'updated_by' => Auth::id(),
            ]);
        });
    }

    public function reverseElectronicInvoiceExit(ElectronicInvoice $invoice, ?string $reason = null): void
    {
        DB::transaction(function () use ($invoice, $reason) {
            $invoice = ElectronicInvoice::withTrashed()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $movements = WarehouseKardexMovement::query()
                ->where('source_type', ElectronicInvoice::class)
                ->where('source_id', $invoice->id)
                ->where('operation_type', 'electronic_invoice')
                ->where('movement_type', 'exit')
                ->where('status', self::STATUS_REGISTERED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($movements as $movement) {
                $stock = WarehouseStock::query()
                    ->whereKey($movement->warehouse_stock_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $quantity = round((float) $movement->quantity_out, 4);
                $costIn = round((float) $movement->total_cost_out, 2);
                $newQuantity = round((float) $stock->current_quantity + $quantity, 4);
                $newTotalCost = round((float) $stock->total_cost + $costIn, 2);
                $averageCost = $this->calculateAverageCost($newTotalCost, $newQuantity);

                $stock->update([
                    'current_quantity' => $newQuantity,
                    'average_unit_cost' => $averageCost,
                    'total_cost' => $newTotalCost,
                    'updated_by' => Auth::id(),
                ]);

                WarehouseKardexMovement::create([
                    'movement_number' => $this->generateMovementNumber(),
                    'warehouse_stock_id' => $stock->id,
                    'warehouse_id' => $movement->warehouse_id,
                    'article_id' => $movement->article_id,
                    'unit_id' => $movement->unit_id,
                    'presentation_id' => $movement->presentation_id,
                    'brand_id' => $movement->brand_id,
                    'lot_number' => $movement->lot_number,
                    'expiration_date' => $movement->expiration_date,
                    'origin' => $movement->origin,
                    'cost_type' => $movement->cost_type,
                    'movement_date' => now(),
                    'movement_type' => 'exit_reversal',
                    'operation_type' => 'electronic_invoice_cancel',
                    'source_type' => ElectronicInvoice::class,
                    'source_id' => $invoice->id,
                    'source_item_type' => $movement->source_item_type,
                    'source_item_id' => $movement->source_item_id,
                    'source_key' => $this->sourceKey('invoice-exit-reversal', $movement->id),
                    'document_type' => $movement->document_type,
                    'document_series' => $movement->document_series,
                    'document_number' => $movement->document_number,
                    'related_party_type' => 'customer',
                    'related_party_id' => $invoice->customer_id,
                    'related_party_name' => $invoice->client_name,
                    'quantity_in' => $quantity,
                    'quantity_out' => 0,
                    'balance_quantity' => $newQuantity,
                    'unit_cost' => $movement->unit_cost,
                    'total_cost_in' => $costIn,
                    'total_cost_out' => 0,
                    'average_unit_cost' => $averageCost,
                    'balance_total_cost' => $newTotalCost,
                    'currency_id' => $movement->currency_id,
                    'exchange_rate' => $movement->exchange_rate,
                    'observations' => $reason ?: 'Anulación de salida por comprobante electrónico',
                    'status' => self::STATUS_REGISTERED,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $movement->update([
                    'status' => self::STATUS_REVERSED,
                    'source_key' => $movement->source_key
                        ? $movement->source_key.':reversed:'.$movement->id
                        : null,
                    'updated_by' => Auth::id(),
                ]);
            }

            if ($movements->isNotEmpty() || $invoice->stock_moved_at) {
                $invoice->update([
                    'stock_moved_at' => null,
                    'stock_reversed_at' => now(),
                    'updated_by' => Auth::id(),
                ]);
            }
        });
    }

    private function registerElectronicInvoiceItemExit(
        ElectronicInvoice $invoice,
        ElectronicInvoiceItem $item
    ): void {
        $required = round((float) $item->quantity, 4);
        $warehouseId = (int) ($invoice->warehouse_id ?: $invoice->warehouseEntry?->warehouse_id);
        $stocksQuery = WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('article_id', $item->article_id)
            ->where('status', 'ACTIVE')
            ->where('current_quantity', '>', 0);

        if ($item->lot_number) {
            $stocksQuery->whereRaw('UPPER(lot_number) = ?', [mb_strtoupper($item->lot_number, 'UTF-8')]);
        }
        if ($item->expiration_date) {
            $stocksQuery->whereDate('expiration_date', $item->expiration_date);
        }

        $stocks = $stocksQuery
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $available = round((float) $stocks->sum('current_quantity'), 4);

        if ($available < $required) {
            $articleName = $item->article?->billing_name ?: $item->description;
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'No hay stock suficiente para el artículo %s. Stock disponible: %s, cantidad requerida: %s.',
                    $articleName,
                    number_format($available, 4, '.', ''),
                    number_format($required, 4, '.', '')
                ),
            ]);
        }

        $pending = $required;
        $firstMovementId = null;
        foreach ($stocks as $stock) {
            if ($pending <= 0) {
                break;
            }
            $stockQuantity = round((float) $stock->current_quantity, 4);
            $quantityOut = min($pending, $stockQuantity);
            $unitCost = round((float) $stock->average_unit_cost, 6);
            $costOut = round($quantityOut * $unitCost, 2);
            $newQuantity = round($stockQuantity - $quantityOut, 4);
            $newTotalCost = max(round((float) $stock->total_cost - $costOut, 2), 0);
            $averageCost = $this->calculateAverageCost($newTotalCost, $newQuantity);

            $stock->update([
                'current_quantity' => $newQuantity,
                'average_unit_cost' => $averageCost,
                'total_cost' => $newTotalCost,
                'updated_by' => Auth::id(),
            ]);

            $movement = WarehouseKardexMovement::create([
                'movement_number' => $this->generateMovementNumber(),
                'warehouse_stock_id' => $stock->id,
                'warehouse_id' => $warehouseId,
                'article_id' => $item->article_id,
                'unit_id' => $stock->unit_id,
                'presentation_id' => $stock->presentation_id,
                'brand_id' => $stock->brand_id,
                'lot_number' => $stock->lot_number,
                'expiration_date' => $stock->expiration_date,
                'origin' => $stock->origin,
                'cost_type' => $stock->cost_type,
                'movement_date' => now(),
                'movement_type' => 'exit',
                'operation_type' => 'electronic_invoice',
                'source_type' => ElectronicInvoice::class,
                'source_id' => $invoice->id,
                'source_item_type' => ElectronicInvoiceItem::class,
                'source_item_id' => $item->id,
                'source_key' => $this->sourceKey('invoice-exit', $invoice->id, $item->id, $stock->id),
                'document_type' => $invoice->document_type === '01' ? 'FACTURA' : 'BOLETA',
                'document_series' => $invoice->serie,
                'document_number' => $invoice->correlativo,
                'related_party_type' => 'customer',
                'related_party_id' => $invoice->customer_id,
                'related_party_name' => $invoice->client_name,
                'quantity_in' => 0,
                'quantity_out' => $quantityOut,
                'balance_quantity' => $newQuantity,
                'unit_cost' => $unitCost,
                'total_cost_in' => 0,
                'total_cost_out' => $costOut,
                'average_unit_cost' => $averageCost,
                'balance_total_cost' => $newTotalCost,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => 1,
                'observations' => 'Salida por facturación',
                'status' => self::STATUS_REGISTERED,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $firstMovementId ??= $movement->id;
            $pending = round($pending - $quantityOut, 4);
        }

        $item->update(['kardex_movement_id' => $firstMovementId]);
    }

    private function registerEntryItem(WarehouseEntry $entry, WarehouseEntryItem $item): void
    {
        if (! $item->article_id) {
            throw ValidationException::withMessages([
                'items' => 'No se puede generar Kardex sin articulo.',
            ]);
        }

        $exists = WarehouseKardexMovement::query()
            ->where('source_type', WarehouseEntry::class)
            ->where('source_id', $entry->id)
            ->where('source_item_type', WarehouseEntryItem::class)
            ->where('source_item_id', $item->id)
            ->where('status', self::STATUS_REGISTERED)
            ->exists();

        if ($exists) {
            return;
        }

        // El ingreso conserva el costo de compra. Los costos vinculados se registran
        // como movimientos separados sin cantidad para mantener trazabilidad.
        $unitCost = round((float) $item->unit_price, 6);

        $allocations = $item->lots->isNotEmpty()
            ? $item->lots->map(fn ($lot) => [
                'quantity' => (float) $lot->quantity,
                'lot_number' => $lot->lot_code,
                'expiration_date' => $lot->expiration_date,
            ])
            : collect([[
                'quantity' => (float) $item->quantity,
                'lot_number' => $item->lot_number,
                'expiration_date' => $item->expiration_date,
            ]]);

        if (abs((float) $allocations->sum('quantity') - (float) $item->quantity) > 0.0001) {
            throw ValidationException::withMessages([
                'items' => 'La suma de lotes no coincide con la cantidad del detalle de ingreso.',
            ]);
        }

        if ($unitCost < 0) {
            throw ValidationException::withMessages([
                'items' => 'El costo del Kardex no puede ser negativo.',
            ]);
        }

        foreach ($allocations as $allocation) {
            $quantity = round((float) $allocation['quantity'], 4);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['items' => 'La cantidad del Kardex debe ser mayor a cero.']);
            }

            $stockItem = clone $item;
            $stockItem->lot_number = $allocation['lot_number'];
            $stockItem->expiration_date = $allocation['expiration_date'];
            $stock = $this->findOrCreateStock($entry, $stockItem);
            $previousQuantity = round((float) $stock->current_quantity, 4);
            $previousTotalCost = round((float) $stock->total_cost, 2);
            $entryTotalCost = round($quantity * $unitCost, 2);
            $newQuantity = round($previousQuantity + $quantity, 4);
            $newTotalCost = round($previousTotalCost + $entryTotalCost, 2);
            $averageCost = $this->calculateAverageCost($newTotalCost, $newQuantity);

            $stock->update([
                'unit_id' => $item->unit_id,
                'presentation_id' => $item->presentation_id,
                'brand_id' => $item->brand_id,
                'origin' => $this->upperOrNull($item->origin),
                'cost_type' => $this->upperOrNull($item->cost_type),
                'current_quantity' => $newQuantity,
                'average_unit_cost' => $averageCost,
                'total_cost' => $newTotalCost,
                'status' => 'ACTIVE',
                'updated_by' => Auth::id(),
            ]);

            WarehouseKardexMovement::create([
                'movement_number' => $this->generateMovementNumber(),
                'warehouse_stock_id' => $stock->id,
                'warehouse_id' => $entry->warehouse_id,
                'article_id' => $item->article_id,
                'unit_id' => $item->unit_id,
                'presentation_id' => $item->presentation_id,
                'brand_id' => $item->brand_id,
                'lot_number' => $this->upperOrNull($allocation['lot_number']),
                'expiration_date' => $allocation['expiration_date'],
                'origin' => $this->upperOrNull($item->origin),
                'cost_type' => $this->upperOrNull($item->cost_type),
                'movement_date' => now(),
                'movement_type' => 'entry',
                'operation_type' => 'warehouse_entry',
                'source_type' => WarehouseEntry::class,
                'source_id' => $entry->id,
                'source_item_type' => WarehouseEntryItem::class,
                'source_item_id' => $item->id,
                'source_key' => $this->sourceKey('warehouse-entry', $entry->id, $item->id, $stock->id),
                'document_type' => $entry->document_type,
                'document_series' => $entry->document_series,
                'document_number' => $entry->document_number,
                'related_party_type' => 'supplier',
                'related_party_id' => $entry->supplier_id,
                'related_party_name' => $entry->supplier?->short_name ?? $entry->supplier?->business_name,
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'balance_quantity' => $newQuantity,
                'unit_cost' => $unitCost,
                'total_cost_in' => $entryTotalCost,
                'total_cost_out' => 0,
                'average_unit_cost' => $averageCost,
                'balance_total_cost' => $newTotalCost,
                'currency_id' => $entry->currency_id,
                'exchange_rate' => 1,
                'observations' => $entry->observations,
                'status' => self::STATUS_REGISTERED,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        }
    }

    private function reverseEntryMovement(WarehouseKardexMovement $movement, WarehouseEntry $entry, ?string $reason): void
    {
        $stock = WarehouseStock::query()
            ->whereKey($movement->warehouse_stock_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            throw ValidationException::withMessages([
                'kardex' => 'No se encontro el stock relacionado al movimiento Kardex.',
            ]);
        }

        $quantity = round((float) $movement->quantity_in, 4);
        $currentQuantity = round((float) $stock->current_quantity, 4);

        if ($currentQuantity < $quantity) {
            throw ValidationException::withMessages([
                'kardex' => 'No se puede revertir el ingreso porque el stock disponible es insuficiente.',
            ]);
        }

        $costOut = round((float) $movement->total_cost_in, 2);
        $newQuantity = round($currentQuantity - $quantity, 4);
        $newTotalCost = max(round((float) $stock->total_cost - $costOut, 2), 0);
        $averageCost = $this->calculateAverageCost($newTotalCost, $newQuantity);

        $stock->update([
            'current_quantity' => $newQuantity,
            'average_unit_cost' => $averageCost,
            'total_cost' => $newTotalCost,
            'updated_by' => Auth::id(),
        ]);

        WarehouseKardexMovement::create([
            'movement_number' => $this->generateMovementNumber(),
            'warehouse_stock_id' => $stock->id,
            'warehouse_id' => $movement->warehouse_id,
            'article_id' => $movement->article_id,
            'unit_id' => $movement->unit_id,
            'presentation_id' => $movement->presentation_id,
            'brand_id' => $movement->brand_id,
            'lot_number' => $movement->lot_number,
            'expiration_date' => $movement->expiration_date,
            'origin' => $movement->origin,
            'cost_type' => $movement->cost_type,
            'movement_date' => now(),
            'movement_type' => $quantity > 0 ? 'reversal' : 'cost_reversal',
            'operation_type' => $quantity > 0 ? 'warehouse_entry_cancel' : 'warehouse_entry_linked_cost_cancel',
            'source_type' => WarehouseEntry::class,
            'source_id' => $entry->id,
            'source_item_type' => $movement->source_item_type,
            'source_item_id' => $movement->source_item_id,
            'source_key' => $this->sourceKey('reversal', $movement->id),
            'document_type' => $movement->document_type,
            'document_series' => $movement->document_series,
            'document_number' => $movement->document_number,
            'related_party_type' => $movement->related_party_type,
            'related_party_id' => $movement->related_party_id,
            'related_party_name' => $movement->related_party_name,
            'quantity_in' => 0,
            'quantity_out' => $quantity,
            'balance_quantity' => $newQuantity,
            'unit_cost' => $movement->unit_cost,
            'total_cost_in' => 0,
            'total_cost_out' => $costOut,
            'average_unit_cost' => $averageCost,
            'balance_total_cost' => $newTotalCost,
            'currency_id' => $movement->currency_id,
            'exchange_rate' => $movement->exchange_rate,
            'observations' => $reason,
            'status' => self::STATUS_REGISTERED,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $movement->update([
            'status' => self::STATUS_REVERSED,
            'source_key' => $movement->source_key
                ? $movement->source_key.':reversed:'.$movement->id
                : null,
            'updated_by' => Auth::id(),
        ]);
    }

    private function findOrCreateStock(WarehouseEntry $entry, WarehouseEntryItem $item): WarehouseStock
    {
        $stockKey = $this->buildStockKey(
            (int) $entry->warehouse_id,
            (int) $item->article_id,
            $item->lot_number,
            $item->expiration_date
        );

        return WarehouseStock::query()->firstOrCreate(
            ['stock_key' => $stockKey],
            [
                'warehouse_id' => $entry->warehouse_id,
                'article_id' => $item->article_id,
                'unit_id' => $item->unit_id,
                'presentation_id' => $item->presentation_id,
                'brand_id' => $item->brand_id,
                'lot_number' => $this->upperOrNull($item->lot_number),
                'expiration_date' => $item->expiration_date,
                'origin' => $this->upperOrNull($item->origin),
                'cost_type' => $this->upperOrNull($item->cost_type),
                'current_quantity' => 0,
                'reserved_quantity' => 0,
                'average_unit_cost' => 0,
                'total_cost' => 0,
                'min_stock' => $item->article?->minimum_stock,
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );
    }

    public function calculateAverageCost(float $totalCost, float $quantity): float
    {
        return $quantity > 0
            ? round($totalCost / $quantity, 6)
            : 0;
    }

    public function registerAdjustment(
        WarehouseStock $stock,
        string $type,
        float $quantity,
        ?float $unitCost,
        string $observations,
        ?string $sourceKey = null
    ): WarehouseKardexMovement {
        if (! in_array($type, ['adjustment_in', 'adjustment_out'], true) || $quantity <= 0) {
            throw ValidationException::withMessages([
                'adjustment' => 'El tipo y la cantidad del ajuste de inventario no son válidos.',
            ]);
        }

        return DB::transaction(function () use ($stock, $type, $quantity, $unitCost, $observations, $sourceKey) {
            $stock = WarehouseStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            if ($sourceKey && ($existing = WarehouseKardexMovement::query()->where('source_key', $sourceKey)->first())) {
                return $existing;
            }

            $quantity = round($quantity, 4);
            $isEntry = $type === 'adjustment_in';
            if (! $isEntry && (float) $stock->current_quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'El ajuste supera el stock disponible.']);
            }

            $appliedUnitCost = $isEntry
                ? round(max((float) $unitCost, 0), 6)
                : round((float) $stock->average_unit_cost, 6);
            $movementCost = round($quantity * $appliedUnitCost, 2);
            $newQuantity = round((float) $stock->current_quantity + ($isEntry ? $quantity : -$quantity), 4);
            $newTotalCost = round((float) $stock->total_cost + ($isEntry ? $movementCost : -$movementCost), 2);
            if ($newQuantity <= 0) {
                $newQuantity = 0;
                $newTotalCost = 0;
            }
            $average = $this->calculateAverageCost($newTotalCost, $newQuantity);

            $stock->update([
                'current_quantity' => $newQuantity,
                'average_unit_cost' => $average,
                'total_cost' => $newTotalCost,
                'updated_by' => Auth::id(),
            ]);

            return WarehouseKardexMovement::create([
                'movement_number' => $this->generateMovementNumber(),
                'warehouse_stock_id' => $stock->id,
                'warehouse_id' => $stock->warehouse_id,
                'article_id' => $stock->article_id,
                'unit_id' => $stock->unit_id,
                'presentation_id' => $stock->presentation_id,
                'brand_id' => $stock->brand_id,
                'lot_number' => $stock->lot_number,
                'expiration_date' => $stock->expiration_date,
                'origin' => $stock->origin,
                'cost_type' => $stock->cost_type,
                'movement_date' => now(),
                'movement_type' => $type,
                'operation_type' => 'manual_adjustment',
                'source_key' => $sourceKey,
                'quantity_in' => $isEntry ? $quantity : 0,
                'quantity_out' => $isEntry ? 0 : $quantity,
                'balance_quantity' => $newQuantity,
                'unit_cost' => $appliedUnitCost,
                'total_cost_in' => $isEntry ? $movementCost : 0,
                'total_cost_out' => $isEntry ? 0 : $movementCost,
                'average_unit_cost' => $average,
                'balance_total_cost' => $newTotalCost,
                'observations' => $observations,
                'status' => self::STATUS_REGISTERED,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Registra los gastos aprobados que afectan inventario como incrementos de valor,
     * sin alterar la cantidad. La distribución por lote respeta el valor comprado.
     */
    public function syncLinkedCosts(WarehouseEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $entry->loadMissing([
                'currency',
                'expenses.distributions.item.lots',
            ]);

            $validKeys = [];
            $expenses = $entry->expenses
                ->where('status', 'ACTIVE')
                ->where('approval_status', 'approved')
                ->where('affects_inventory_cost', true);

            foreach ($expenses as $expense) {
                foreach ($expense->distributions as $distribution) {
                    $entryMovements = WarehouseKardexMovement::query()
                        ->where('source_type', WarehouseEntry::class)
                        ->where('source_id', $entry->id)
                        ->where('source_item_type', WarehouseEntryItem::class)
                        ->where('source_item_id', $distribution->warehouse_entry_item_id)
                        ->where('operation_type', 'warehouse_entry')
                        ->where('status', self::STATUS_REGISTERED)
                        ->orderBy('id')
                        ->get();

                    if ($entryMovements->isEmpty()) {
                        continue;
                    }

                    $allocations = $this->proportionalAllocation(
                        (float) $distribution->distributed_amount,
                        $entryMovements->map(fn ($movement) => (float) $movement->total_cost_in)->all()
                    );

                    foreach ($entryMovements as $position => $entryMovement) {
                        $amount = $allocations[$position] ?? 0;
                        if ($amount <= 0) {
                            continue;
                        }

                        $sourceKey = $this->sourceKey(
                            'warehouse-linked-cost',
                            $distribution->id,
                            $entryMovement->warehouse_stock_id
                        );
                        $validKeys[] = $sourceKey;

                        if (WarehouseKardexMovement::query()->where('source_key', $sourceKey)->exists()) {
                            continue;
                        }

                        $stock = WarehouseStock::query()
                            ->whereKey($entryMovement->warehouse_stock_id)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $newTotalCost = round((float) $stock->total_cost + $amount, 2);
                        $averageCost = $this->calculateAverageCost($newTotalCost, (float) $stock->current_quantity);

                        $stock->update([
                            'total_cost' => $newTotalCost,
                            'average_unit_cost' => $averageCost,
                            'updated_by' => Auth::id(),
                        ]);

                        WarehouseKardexMovement::create([
                            'movement_number' => $this->generateMovementNumber(),
                            'warehouse_stock_id' => $stock->id,
                            'warehouse_id' => $stock->warehouse_id,
                            'article_id' => $stock->article_id,
                            'unit_id' => $stock->unit_id,
                            'presentation_id' => $stock->presentation_id,
                            'brand_id' => $stock->brand_id,
                            'lot_number' => $stock->lot_number,
                            'expiration_date' => $stock->expiration_date,
                            'origin' => $stock->origin,
                            'cost_type' => $stock->cost_type,
                            'movement_date' => now(),
                            'movement_type' => 'linked_cost',
                            'operation_type' => 'warehouse_entry_linked_cost',
                            'source_type' => WarehouseEntry::class,
                            'source_id' => $entry->id,
                            'source_item_type' => WarehouseEntryExpenseDistribution::class,
                            'source_item_id' => $distribution->id,
                            'source_key' => $sourceKey,
                            'document_type' => $expense->document_type,
                            'document_series' => $expense->document_series,
                            'document_number' => $expense->document_number,
                            'related_party_type' => 'supplier',
                            'related_party_id' => $expense->provider_id,
                            'related_party_name' => $expense->provider_name,
                            'quantity_in' => 0,
                            'quantity_out' => 0,
                            'balance_quantity' => $stock->current_quantity,
                            'unit_cost' => 0,
                            'total_cost_in' => $amount,
                            'total_cost_out' => 0,
                            'average_unit_cost' => $averageCost,
                            'balance_total_cost' => $newTotalCost,
                            'currency_id' => $expense->currency_id ?: $entry->currency_id,
                            'exchange_rate' => 1,
                            'observations' => trim('Costo vinculado: '.($expense->description ?: $expense->expense_type)),
                            'status' => self::STATUS_REGISTERED,
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),
                        ]);
                    }
                }
            }

            $staleMovements = WarehouseKardexMovement::query()
                ->where('source_type', WarehouseEntry::class)
                ->where('source_id', $entry->id)
                ->where('operation_type', 'warehouse_entry_linked_cost')
                ->where('status', self::STATUS_REGISTERED)
                ->when($validKeys, fn ($query) => $query->whereNotIn('source_key', $validKeys))
                ->when(! $validKeys, fn ($query) => $query)
                ->lockForUpdate()
                ->get();

            foreach ($staleMovements as $movement) {
                $this->reverseEntryMovement($movement, $entry, 'Reversión de costo vinculado no vigente');
            }
        });
    }

    public function stockAtDateQuery(string $date, ?int $warehouseId = null, ?int $articleId = null): Builder
    {
        $limit = $date.' 23:59:59';

        return WarehouseKardexMovement::query()
            ->where('status', '!=', 'cancelled')
            ->where('movement_date', '<=', $limit)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($articleId, fn ($query) => $query->where('article_id', $articleId))
            ->whereNotExists(function ($query) use ($limit) {
                $query->selectRaw('1')
                    ->from('warehouse_kardex_movements as later')
                    ->whereColumn('later.warehouse_stock_id', 'warehouse_kardex_movements.warehouse_stock_id')
                    ->where('later.status', '!=', 'cancelled')
                    ->where('later.movement_date', '<=', $limit)
                    ->where(function ($query) {
                        $query->whereColumn('later.movement_date', '>', 'warehouse_kardex_movements.movement_date')
                            ->orWhere(function ($query) {
                                $query->whereColumn('later.movement_date', 'warehouse_kardex_movements.movement_date')
                                    ->whereColumn('later.id', '>', 'warehouse_kardex_movements.id');
                            });
                    });
            });
    }

    public function recalculate(array $filters = []): WarehouseKardexRecalculation
    {
        return DB::transaction(function () use ($filters) {
            $log = WarehouseKardexRecalculation::create([
                'warehouse_id' => $filters['warehouse_id'] ?? null,
                'article_id' => $filters['article_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'started_at' => now(),
                'created_by' => Auth::id(),
            ]);

            $stocks = WarehouseStock::query()
                ->when($log->warehouse_id, fn ($query, $value) => $query->where('warehouse_id', $value))
                ->when($log->article_id, fn ($query, $value) => $query->where('article_id', $value))
                ->when($log->date_from || $log->date_to, function ($query) use ($log) {
                    $query->whereHas('movements', function ($query) use ($log) {
                        $query->when($log->date_from, fn ($query, $value) => $query->whereDate('movement_date', '>=', $value))
                            ->when($log->date_to, fn ($query, $value) => $query->whereDate('movement_date', '<=', $value));
                    });
                })
                ->lockForUpdate()
                ->get();
            $movementCount = 0;

            foreach ($stocks as $stock) {
                $quantity = 0.0;
                $totalCost = 0.0;
                $movements = $stock->movements()
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('movement_date')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($movements as $movement) {
                    $quantityIn = round((float) $movement->quantity_in, 4);
                    $quantityOut = round((float) $movement->quantity_out, 4);
                    $costIn = round((float) $movement->total_cost_in, 2);
                    $averageBefore = $this->calculateAverageCost($totalCost, $quantity);
                    $costOut = $quantityOut > 0
                        ? round($quantityOut * $averageBefore, 2)
                        : round((float) $movement->total_cost_out, 2);

                    $quantity = round($quantity + $quantityIn - $quantityOut, 4);
                    $totalCost = max(round($totalCost + $costIn - $costOut, 2), 0);
                    if ($quantity <= 0) {
                        $quantity = 0;
                        $totalCost = 0;
                    }
                    $average = $this->calculateAverageCost($totalCost, $quantity);

                    $movement->update([
                        'total_cost_out' => $costOut,
                        'unit_cost' => $quantityOut > 0 ? $averageBefore : $movement->unit_cost,
                        'balance_quantity' => $quantity,
                        'average_unit_cost' => $average,
                        'balance_total_cost' => $totalCost,
                        'updated_by' => Auth::id(),
                    ]);
                    $movementCount++;
                }

                $stock->update([
                    'current_quantity' => $quantity,
                    'average_unit_cost' => $this->calculateAverageCost($totalCost, $quantity),
                    'total_cost' => $totalCost,
                    'updated_by' => Auth::id(),
                ]);
            }

            $log->update([
                'stocks_processed' => $stocks->count(),
                'movements_processed' => $movementCount,
                'notes' => 'Recálculo por promedio ponderado móvil completado.',
                'finished_at' => now(),
            ]);

            return $log->fresh();
        });
    }

    public function proportionalAllocation(float $amount, array $weights): array
    {
        if ($amount <= 0 || $weights === []) {
            return array_fill(0, count($weights), 0.0);
        }

        $weights = array_map(fn ($weight) => max((float) $weight, 0), $weights);
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            $weights = array_fill(0, count($weights), 1);
            $totalWeight = count($weights);
        }

        $remaining = (int) round($amount * 100);
        $result = [];
        foreach ($weights as $position => $weight) {
            $cents = $position === array_key_last($weights)
                ? $remaining
                : (int) round(($amount * 100) * ($weight / $totalWeight));
            $result[$position] = (float) ($cents / 100);
            $remaining -= $cents;
        }

        return $result;
    }

    private function sourceKey(string $prefix, int|string ...$parts): string
    {
        return implode(':', [$prefix, ...$parts]);
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'format')
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : mb_strtoupper($value, 'UTF-8');
    }
}

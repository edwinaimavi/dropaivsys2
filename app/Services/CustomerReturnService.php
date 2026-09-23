<?php

namespace App\Services;

use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\ElectronicInvoiceItemDispatchAllocation;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerReturnService
{
    private const SCALE = 4;
    private const MAX_CREATE_ATTEMPTS = 3;

    public function __construct(
        private readonly WarehouseKardexService $kardexService,
        private readonly CustomerPurchaseOrderStatusService $statusService,
        private readonly CustomerReturnDocumentService $documentService,
        private readonly CompanyWarehouseService $companyWarehouseService,
        private readonly ArticleInventoryPolicy $articleInventoryPolicy,
        private readonly SunatUnitPolicy $sunatUnitPolicy,
        private readonly ArticleSunatInventoryCatalogPolicy $sunatInventoryCatalogPolicy,
        private readonly WarehouseValuationPoolService $warehouseValuationPoolService
    ) {}

    public function createDraft(WarehouseDispatch $dispatch, array $data, array $documents = []): CustomerReturn
    {
        $data['idempotency_key'] = $data['idempotency_key'] ?? (string) Str::uuid();
        $userId = Auth::id();
        if (! $userId) {
            throw ValidationException::withMessages(['created_by_user_id' => 'No se pudo identificar al usuario.']);
        }

        for ($attempt = 1; $attempt <= self::MAX_CREATE_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($dispatch, $data, $documents, $userId) {
                    $dispatch = WarehouseDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
                    $this->validateDispatch($dispatch);

                    $existing = CustomerReturn::query()->where('idempotency_key', $data['idempotency_key'])->first();
                    if ($existing) {
                        if ((int) $existing->warehouse_dispatch_id !== (int) $dispatch->id) {
                            throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra devolución.']);
                        }
                        return $this->returnResult($existing);
                    }

                    $rows = $this->validatedRows($dispatch, $data);
                    $return = CustomerReturn::create([
                        'return_number' => $this->generateReturnNumber(),
                        'idempotency_key' => $data['idempotency_key'],
                        'company_id' => $dispatch->company_id,
                        'customer_purchase_order_id' => $dispatch->customer_purchase_order_id,
                        'warehouse_dispatch_id' => $dispatch->id,
                        'warehouse_id' => $dispatch->warehouse_id,
                        'return_date' => $data['return_date'],
                        'status' => CustomerReturn::STATUS_DRAFT,
                        'reason' => $data['reason'],
                        'reason_description' => $data['reason_description'] ?? null,
                        'observation' => $data['observation'] ?? null,
                        'received_by_user_id' => $data['received_by_user_id'] ?? $userId,
                        'created_by_user_id' => $userId,
                        'updated_by_user_id' => $userId,
                    ]);
                    $this->replaceDraftItems($return, $rows);
                    if ($documents) $this->documentService->storeMany($return, $documents, $userId);
                    return $this->returnResult($return);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === self::MAX_CREATE_ATTEMPTS || ! $this->isRetriableCollision($exception)) throw $exception;
            }
        }

        throw ValidationException::withMessages(['return_number' => 'No se pudo generar un número de devolución único.']);
    }

    public function updateDraft(CustomerReturn $return, array $data, array $documents = []): CustomerReturn
    {
        return DB::transaction(function () use ($return, $data, $documents) {
            $return = CustomerReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if (! $return->isDraft()) {
                throw ValidationException::withMessages(['customer_return' => 'Solo las devoluciones en borrador pueden editarse.']);
            }
            $dispatch = WarehouseDispatch::query()->whereKey($return->warehouse_dispatch_id)->lockForUpdate()->firstOrFail();
            $this->validateDispatch($dispatch);
            $this->validateReturnContext($return, $dispatch);
            $rows = $this->validatedRows($dispatch, $data);
            $return->update([
                'return_date' => $data['return_date'], 'reason' => $data['reason'],
                'reason_description' => $data['reason_description'] ?? null,
                'observation' => $data['observation'] ?? null,
                'received_by_user_id' => $data['received_by_user_id'] ?? Auth::id(),
                'updated_by_user_id' => Auth::id(),
            ]);
            $return->items()->delete();
            $this->replaceDraftItems($return, $rows);
            if ($documents) $this->documentService->storeMany($return, $documents, Auth::id());
            return $this->returnResult($return);
        });
    }

    public function confirm(CustomerReturn $return): CustomerReturn
    {
        $userId = Auth::id();
        if (! $userId) throw ValidationException::withMessages(['confirmed_by_user_id' => 'No se pudo identificar al usuario.']);

        return DB::transaction(function () use ($return, $userId) {
            $return = CustomerReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($return->isConfirmed()) return $this->returnResult($return);
            if (! $return->isDraft()) {
                throw ValidationException::withMessages(['customer_return' => 'Solo una devolución en borrador puede confirmarse.']);
            }
            if (in_array($return->reason, CustomerReturn::QUARANTINE_REASONS, true)) {
                throw ValidationException::withMessages([
                    'reason' => 'Los productos dañados o con incidencia de vencimiento/lote requieren un flujo de cuarentena o baja. No pueden reintegrarse al stock disponible.',
                ]);
            }

            $dispatch = WarehouseDispatch::query()->whereKey($return->warehouse_dispatch_id)->lockForUpdate()->firstOrFail();
            $this->validateDispatch($dispatch);
            $this->validateReturnContext($return, $dispatch);
            $order = CustomerPurchaseOrder::query()->whereKey($return->customer_purchase_order_id)->lockForUpdate()->firstOrFail();
            $details = CustomerReturnItem::query()->where('customer_return_id', $return->id)
                ->orderBy('warehouse_stock_id')->orderBy('id')->lockForUpdate()->get();
            if ($details->isEmpty() || $details->contains(fn ($item) => $item->status !== CustomerReturnItem::STATUS_DRAFT || $item->kardex_movement_id)) {
                throw ValidationException::withMessages(['items' => 'El detalle del borrador no se encuentra en un estado válido para confirmar.']);
            }

            $dispatchItems = WarehouseDispatchItem::query()->where('warehouse_dispatch_id', $dispatch->id)
                ->whereIn('id', $details->pluck('warehouse_dispatch_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $this->lockConfirmedReturnItems($dispatchItems->keys());
            $stocks = WarehouseStock::withTrashed()
                ->where('company_id', $return->company_id)
                ->whereIn('id', $details->pluck('warehouse_stock_id')->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $valuationPools = [];

            foreach ($details as $detail) {
                $dispatchItem = $dispatchItems->get((int) $detail->warehouse_dispatch_item_id);
                $stock = $stocks->get((int) $detail->warehouse_stock_id);
                if ($stock) {
                    $this->companyWarehouseService->assertStockOwner($stock, (int) $return->company_id);
                }
                $this->validateDetailTrace($return, $dispatchItem, $stock, $detail);
                $stock->loadMissing('article.unit.sunatUnit.catalog');
                $this->articleInventoryPolicy->assertHasSunatExistenceType($stock->article, 'items');
                $this->sunatUnitPolicy->codeForArticle($stock->article, 'items', true);
                $this->sunatInventoryCatalogPolicy->assertHasInventoryIdentification($stock->article, 'items');
                $available = $this->returnableForItem($dispatchItem, $return->id);
                $quantity = $this->validatedQuantity($detail->quantity);
                if (bccomp($quantity, $available, self::SCALE) > 0) {
                    throw ValidationException::withMessages(['items' => 'La cantidad devolvible cambió. Actualice el borrador antes de confirmar.']);
                }

                $unitCost = round((float) $dispatchItem->unit_cost, 6);
                $totalCost = round((float) $quantity * $unitCost, 2);
                $articleId = (int) $stock->article_id;
                $valuationPools[$articleId] ??= $this->warehouseValuationPoolService->lockPool(
                    (int) $return->company_id,
                    (int) $return->warehouse_id,
                    $articleId,
                    $userId
                );
                $valuationPools[$articleId] = $this->warehouseValuationPoolService->addQuantityAtCost(
                    $valuationPools[$articleId],
                    $quantity,
                    $totalCost,
                    $userId
                );
                $currentQuantity = $this->decimalQuantity($stock->current_quantity);
                $currentTotalCost = bccomp($currentQuantity, '0', self::SCALE) > 0 ? round((float) $stock->total_cost, 2) : 0;
                $newQuantity = bcadd($currentQuantity, $quantity, self::SCALE);
                $newTotalCost = round($currentTotalCost + $totalCost, 2);
                $averageCost = $this->kardexService->calculateAverageCost($newTotalCost, (float) $newQuantity);

                $stock->update(['current_quantity' => $newQuantity, 'total_cost' => $newTotalCost,
                    'average_unit_cost' => $averageCost, 'status' => 'ACTIVE', 'updated_by' => $userId]);
                $movement = WarehouseKardexMovement::create($this->movementData(
                    $return, $detail, $dispatchItem, $stock, $quantity, $unitCost, $totalCost,
                    $newQuantity, $newTotalCost, $averageCost, false, $userId
                ));
                $detail->update(['unit_cost_snapshot' => $unitCost, 'total_cost' => $totalCost,
                    'kardex_movement_id' => $movement->id, 'status' => CustomerReturnItem::STATUS_CONFIRMED]);
            }

            $return->update(['status' => CustomerReturn::STATUS_CONFIRMED, 'confirmed_at' => now(),
                'confirmed_by_user_id' => $userId, 'updated_by_user_id' => $userId]);
            $this->statusService->syncStatus($order);
            return $this->returnResult($return);
        });
    }

    public function cancelDraft(CustomerReturn $return, string $reason): CustomerReturn
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Ingrese un motivo de cancelación de 5 a 2000 caracteres.']);
        }
        return DB::transaction(function () use ($return, $reason) {
            $return = CustomerReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($return->isCancelled()) return $this->returnResult($return);
            if (! $return->isDraft()) throw ValidationException::withMessages(['customer_return' => 'Solo un borrador puede cancelarse sin reversa.']);
            $return->items()->update(['status' => CustomerReturnItem::STATUS_CANCELLED]);
            $return->update(['status' => CustomerReturn::STATUS_CANCELLED, 'cancelled_at' => now(),
                'cancelled_by_user_id' => Auth::id(), 'cancellation_reason' => trim($reason), 'updated_by_user_id' => Auth::id()]);
            return $this->returnResult($return);
        });
    }

    public function reverse(CustomerReturn $return, string $reason): CustomerReturn
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Ingrese un motivo de reversa de 5 a 2000 caracteres.']);
        }
        $userId = Auth::id();
        if (! $userId) throw ValidationException::withMessages(['reversed_by_user_id' => 'No se pudo identificar al usuario.']);
        return DB::transaction(function () use ($return, $reason, $userId) {
            $return = CustomerReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if (! $return->isConfirmed()) throw ValidationException::withMessages(['customer_return' => 'Solo una devolución confirmada puede reversarse.']);
            $dispatch = WarehouseDispatch::query()->whereKey($return->warehouse_dispatch_id)->lockForUpdate()->firstOrFail();
            $this->validateDispatch($dispatch);
            $this->validateReturnContext($return, $dispatch);
            $order = CustomerPurchaseOrder::query()->whereKey($return->customer_purchase_order_id)->lockForUpdate()->firstOrFail();
            $details = CustomerReturnItem::query()->where('customer_return_id', $return->id)
                ->orderBy('warehouse_stock_id')->orderBy('id')->lockForUpdate()->get();
            $stocks = WarehouseStock::withTrashed()
                ->where('company_id', $return->company_id)
                ->whereIn('id', $details->pluck('warehouse_stock_id')->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $valuationPools = [];
            foreach ($details as $detail) {
                $stock = $stocks->get((int) $detail->warehouse_stock_id);
                if ($stock) {
                    $this->companyWarehouseService->assertStockOwner($stock, (int) $return->company_id);
                }
                $quantity = $this->validatedQuantity($detail->quantity);
                if (! $stock || bccomp($this->decimalQuantity($stock->current_quantity), $quantity, self::SCALE) < 0) {
                    throw ValidationException::withMessages(['customer_return' => 'No existe stock suficiente en el lote para reversar la devolución sin producir saldo negativo.']);
                }
            }
            foreach ($details as $detail) {
                $stock = $stocks->get((int) $detail->warehouse_stock_id);
                $dispatchItem = WarehouseDispatchItem::query()->findOrFail($detail->warehouse_dispatch_item_id);
                $quantity = $this->validatedQuantity($detail->quantity);
                $totalCost = round((float) $detail->total_cost, 2);
                $articleId = (int) $stock->article_id;
                $valuationPools[$articleId] ??= $this->warehouseValuationPoolService->lockPool(
                    (int) $return->company_id,
                    (int) $return->warehouse_id,
                    $articleId,
                    $userId
                );
                $valuationPools[$articleId] = $this->warehouseValuationPoolService->removeQuantityAtHistoricalCost(
                    $valuationPools[$articleId],
                    $quantity,
                    $totalCost,
                    $userId
                );
                $newQuantity = bcsub($this->decimalQuantity($stock->current_quantity), $quantity, self::SCALE);
                $newTotalCost = max(round((float) $stock->total_cost - $totalCost, 2), 0);
                if (bccomp($newQuantity, '0', self::SCALE) === 0) $newTotalCost = 0;
                $averageCost = $this->kardexService->calculateAverageCost($newTotalCost, (float) $newQuantity);
                $stock->update(['current_quantity' => $newQuantity, 'total_cost' => $newTotalCost,
                    'average_unit_cost' => $averageCost, 'updated_by' => $userId]);
                $movement = WarehouseKardexMovement::create($this->movementData(
                    $return, $detail, $dispatchItem, $stock, $quantity, (float) $detail->unit_cost_snapshot,
                    $totalCost, $newQuantity, $newTotalCost, $averageCost, true, $userId, trim($reason)
                ));
                $detail->update(['reversal_kardex_movement_id' => $movement->id, 'status' => CustomerReturnItem::STATUS_REVERSED]);
            }
            $return->update(['status' => CustomerReturn::STATUS_REVERSED, 'reversed_at' => now(),
                'reversed_by_user_id' => $userId, 'reversal_reason' => trim($reason), 'updated_by_user_id' => $userId]);
            $this->statusService->syncStatus($order);
            return $this->returnResult($return);
        });
    }

    public function returnableQuantities(WarehouseDispatch $dispatch): Collection
    {
        $items = $dispatch->items()->get();
        $returned = $this->confirmedReturnedQuantities($items->pluck('id'));
        return $items->mapWithKeys(fn ($item) => [$item->id => (float) $this->subtractQuantity(
            $this->decimalQuantity($item->quantity), $this->decimalQuantity($returned->get($item->id, 0))
        )]);
    }

    public function confirmedReturnedQuantities(Collection $dispatchItemIds): Collection
    {
        if ($dispatchItemIds->isEmpty()) return collect();
        return CustomerReturnItem::query()->join('customer_returns', 'customer_returns.id', '=', 'customer_return_items.customer_return_id')
            ->whereIn('customer_return_items.warehouse_dispatch_item_id', $dispatchItemIds)
            ->where('customer_returns.status', CustomerReturn::STATUS_CONFIRMED)
            ->whereNull('customer_returns.deleted_at')
            ->where('customer_return_items.status', CustomerReturnItem::STATUS_CONFIRMED)
            ->groupBy('customer_return_items.warehouse_dispatch_item_id')
            ->selectRaw('customer_return_items.warehouse_dispatch_item_id, SUM(customer_return_items.quantity) total')
            ->pluck('total', 'warehouse_dispatch_item_id');
    }

    public function invoiceContext(Collection $dispatchItemIds): Collection
    {
        if ($dispatchItemIds->isEmpty()) return collect();
        return ElectronicInvoiceItemDispatchAllocation::query()
            ->join('electronic_invoice_items as invoice_items', 'invoice_items.id', '=', 'electronic_invoice_item_dispatch_allocations.electronic_invoice_item_id')
            ->join('electronic_invoices as invoices', 'invoices.id', '=', 'invoice_items.electronic_invoice_id')
            ->whereIn('electronic_invoice_item_dispatch_allocations.warehouse_dispatch_item_id', $dispatchItemIds)
            ->whereNull('invoices.deleted_at')->whereNotIn('invoices.status', ['draft', 'cancelled', 'voided'])->where('invoices.is_voided', false)
            ->get(['electronic_invoice_item_dispatch_allocations.warehouse_dispatch_item_id',
                'electronic_invoice_item_dispatch_allocations.quantity', 'invoices.id', 'invoices.full_number',
                'invoices.payment_status', 'invoices.paid_amount', 'invoices.pending_amount']);
    }

    private function validatedRows(WarehouseDispatch $dispatch, array $data): Collection
    {
        $this->validateReason($data);
        $rows = collect($data['items'] ?? [])->map(fn ($item) => [
            'warehouse_dispatch_item_id' => (int) ($item['warehouse_dispatch_item_id'] ?? 0),
            'quantity' => $this->validatedQuantity($item['quantity'] ?? null),
        ])->filter(fn ($item) => $item['warehouse_dispatch_item_id'] > 0)->values();
        if ($rows->isEmpty()) throw ValidationException::withMessages(['items' => 'Registre al menos una cantidad a devolver.']);
        if ($rows->pluck('warehouse_dispatch_item_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Un detalle de la salida no puede repetirse.']);
        }
        $dispatchItems = WarehouseDispatchItem::query()->where('warehouse_dispatch_id', $dispatch->id)
            ->whereIn('id', $rows->pluck('warehouse_dispatch_item_id'))->with('article')->lockForUpdate()->get()->keyBy('id');
        $returned = $this->confirmedReturnedQuantities($dispatchItems->keys());
        foreach ($rows as $row) {
            $item = $dispatchItems->get($row['warehouse_dispatch_item_id']);
            if (! $item || ! in_array($item->status, [WarehouseDispatchItem::STATUS_CONFIRMED, WarehouseDispatchItem::STATUS_LEGACY_CONFIRMED], true)) {
                throw ValidationException::withMessages(['items' => 'Todos los artículos deben pertenecer a la SAL confirmada.']);
            }
            $this->articleInventoryPolicy->assertCanParticipateInInventory($item->article, 'items');
            $available = $this->subtractQuantity($this->decimalQuantity($item->quantity), $this->decimalQuantity($returned->get($item->id, 0)));
            if (bccomp($row['quantity'], $available, self::SCALE) > 0) {
                throw ValidationException::withMessages(['items' => "La cantidad a devolver supera el disponible del detalle ({$available})."]);
            }
        }
        return $rows->map(fn ($row) => $row + ['dispatch_item' => $dispatchItems->get($row['warehouse_dispatch_item_id'])]);
    }

    private function replaceDraftItems(CustomerReturn $return, Collection $rows): void
    {
        foreach ($rows as $row) {
            $item = $row['dispatch_item'];
            CustomerReturnItem::create([
                'customer_return_id' => $return->id, 'warehouse_dispatch_item_id' => $item->id,
                'warehouse_stock_id' => $item->warehouse_stock_id, 'article_id' => $item->article_id,
                'unit_id' => $item->unit_id, 'presentation_id' => $item->presentation_id, 'brand_id' => $item->brand_id,
                'lot_number_snapshot' => $item->lot_number, 'expiration_date_snapshot' => $item->expiration_date,
                'quantity' => $row['quantity'], 'unit_cost_snapshot' => $item->unit_cost,
                'total_cost' => round((float) $row['quantity'] * (float) $item->unit_cost, 2),
                'status' => CustomerReturnItem::STATUS_DRAFT,
            ]);
        }
    }

    private function validateDispatch(WarehouseDispatch $dispatch): void
    {
        if (! $dispatch->isConfirmed()) throw ValidationException::withMessages(['warehouse_dispatch_id' => 'Solo una SAL confirmada admite devoluciones de cliente.']);
        if ((int) $dispatch->company_id !== (int) $dispatch->customerPurchaseOrder()->value('company_id')) {
            throw ValidationException::withMessages(['warehouse_dispatch_id' => 'La empresa de la SAL no coincide con la OC Cliente.']);
        }
    }

    private function validateReturnContext(CustomerReturn $return, WarehouseDispatch $dispatch): void
    {
        if ((int) $return->company_id !== (int) $dispatch->company_id
            || (int) $return->customer_purchase_order_id !== (int) $dispatch->customer_purchase_order_id
            || (int) $return->warehouse_id !== (int) $dispatch->warehouse_id) {
            throw ValidationException::withMessages([
                'customer_return' => 'La empresa, OC Cliente o almacén de la devolución no coincide con la SAL original.',
            ]);
        }
    }

    private function validateDetailTrace(CustomerReturn $return, ?WarehouseDispatchItem $item, ?WarehouseStock $stock, CustomerReturnItem $detail): void
    {
        if (! $item || ! $stock || (int) $return->warehouse_id !== (int) $stock->warehouse_id
            || (int) $return->company_id !== (int) $stock->company_id
            || (int) $detail->warehouse_stock_id !== (int) $item->warehouse_stock_id
            || (int) $detail->article_id !== (int) $item->article_id
            || (int) $stock->article_id !== (int) $item->article_id
            || (string) ($detail->lot_number_snapshot ?? '') !== (string) ($item->lot_number ?? '')
            || $detail->expiration_date_snapshot?->format('Y-m-d') !== $item->expiration_date?->format('Y-m-d')) {
            throw ValidationException::withMessages(['items' => 'La trazabilidad de artículo, lote o almacén cambió. No se confirmó la devolución.']);
        }
    }

    private function validateReason(array $data): void
    {
        if (! isset(CustomerReturn::REASONS[$data['reason'] ?? ''])) throw ValidationException::withMessages(['reason' => 'Seleccione un motivo válido.']);
        if (($data['reason'] ?? null) === 'other' && blank($data['reason_description'] ?? null)) {
            throw ValidationException::withMessages(['reason_description' => 'Describa el motivo cuando selecciona Otro.']);
        }
    }

    private function lockConfirmedReturnItems(Collection $dispatchItemIds): Collection
    {
        return CustomerReturnItem::query()->select('customer_return_items.*')
            ->join('customer_returns', 'customer_returns.id', '=', 'customer_return_items.customer_return_id')
            ->whereIn('customer_return_items.warehouse_dispatch_item_id', $dispatchItemIds)
            ->where('customer_returns.status', CustomerReturn::STATUS_CONFIRMED)
            ->whereNull('customer_returns.deleted_at')->orderBy('customer_return_items.id')->lockForUpdate()->get();
    }

    private function returnableForItem(WarehouseDispatchItem $item, ?int $exceptReturnId = null): string
    {
        $query = CustomerReturnItem::query()->join('customer_returns', 'customer_returns.id', '=', 'customer_return_items.customer_return_id')
            ->where('customer_return_items.warehouse_dispatch_item_id', $item->id)
            ->where('customer_returns.status', CustomerReturn::STATUS_CONFIRMED)->whereNull('customer_returns.deleted_at');
        if ($exceptReturnId) $query->where('customer_returns.id', '!=', $exceptReturnId);
        return $this->subtractQuantity($this->decimalQuantity($item->quantity), $this->decimalQuantity($query->sum('customer_return_items.quantity')));
    }

    private function movementData(CustomerReturn $return, CustomerReturnItem $detail, WarehouseDispatchItem $dispatchItem,
        WarehouseStock $stock, string $quantity, float $unitCost, float $totalCost, string $balanceQuantity,
        float $balanceTotalCost, float $averageCost, bool $reversal, ?int $userId, ?string $reversalReason = null): array
    {
        $order = $return->customerPurchaseOrder()->with('customer')->first();
        $originalMovementId = $reversal
            ? $detail->kardex_movement_id
            : $dispatchItem->kardex_movement_id;
        $originalMovement = $originalMovementId
            ? WarehouseKardexMovement::query()->find($originalMovementId)
            : null;
        $accountingSnapshots = $this->kardexService->accountingSnapshots(
            (int) $return->company_id,
            (int) $return->warehouse_id,
            (int) $stock->article_id,
            $stock->unit_id ? (int) $stock->unit_id : null,
            $originalMovement
        );
        $sunatDocumentOperationSnapshots = $this->kardexService->sunatDocumentOperationSnapshots(
            $reversal ? 'exit' : 'entry',
            $reversal ? 'customer_return_reversal' : 'customer_return',
            originalMovement: $reversal ? $originalMovement : null
        );

        return [
            'movement_number' => $this->kardexService->generateMovementNumber(),
            'company_id' => $return->company_id,
            'warehouse_stock_id' => $stock->id, 'warehouse_id' => $return->warehouse_id,
            'article_id' => $stock->article_id, 'unit_id' => $stock->unit_id,
            ...$accountingSnapshots,
            ...$sunatDocumentOperationSnapshots,
            'presentation_id' => $stock->presentation_id, 'brand_id' => $stock->brand_id,
            'lot_number' => $stock->lot_number, 'expiration_date' => $stock->expiration_date,
            'origin' => $stock->origin, 'cost_type' => $stock->cost_type,
            'movement_date' => $reversal ? now() : $return->return_date,
            'movement_type' => $reversal ? 'exit' : 'entry',
            'operation_type' => $reversal ? 'customer_return_reversal' : 'customer_return',
            'source_type' => CustomerReturn::class, 'source_id' => $return->id,
            'source_item_type' => CustomerReturnItem::class, 'source_item_id' => $detail->id,
            'source_key' => $reversal ? "customer-return-reversal:{$return->id}:{$detail->id}" : "customer-return:{$return->id}:{$detail->id}",
            'document_type' => $reversal ? 'REVERSA DEVOLUCIÓN CLIENTE' : 'DEVOLUCIÓN CLIENTE',
            'document_number' => $return->return_number, 'related_party_type' => 'customer',
            'related_party_id' => $order?->customer_id,
            'related_party_name' => $order?->customer?->business_name ?: $order?->customer?->full_name ?: 'CLIENTE',
            'quantity_in' => $reversal ? 0 : $quantity, 'quantity_out' => $reversal ? $quantity : 0,
            'balance_quantity' => $balanceQuantity, 'unit_cost' => $unitCost,
            'total_cost_in' => $reversal ? 0 : $totalCost, 'total_cost_out' => $reversal ? $totalCost : 0,
            'average_unit_cost' => $averageCost, 'balance_total_cost' => $balanceTotalCost,
            'currency_id' => $order?->currency_id, 'exchange_rate' => 1,
            'observations' => $reversal ? $reversalReason : ($return->observation ?: CustomerReturn::REASONS[$return->reason]),
            'status' => 'registered', 'created_by' => $userId, 'updated_by' => $userId,
        ];
    }

    private function returnResult(CustomerReturn $return): CustomerReturn
    {
        return $return->fresh(['company', 'customerPurchaseOrder.customer', 'warehouseDispatch', 'warehouse',
            'receivedBy', 'creator', 'confirmedBy', 'cancelledBy', 'reversedBy',
            'items.warehouseDispatchItem.customerPurchaseOrderItem.article', 'items.article']);
    }

    private function validatedQuantity(mixed $value): string
    {
        $quantity = trim((string) $value);
        if ($quantity === '' || ! preg_match('/^\d+(?:\.\d{1,4})?$/', $quantity) || bccomp($quantity, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages(['items' => 'Cada cantidad a devolver debe ser mayor a 0 y tener como máximo 4 decimales.']);
        }
        return bcadd($quantity, '0', self::SCALE);
    }

    private function decimalQuantity(mixed $value): string { return bcadd((string) ($value ?? 0), '0', self::SCALE); }
    private function subtractQuantity(string $a, string $b): string
    {
        $result = bcsub($a, $b, self::SCALE);
        return bccomp($result, '0', self::SCALE) < 0 ? '0.0000' : $result;
    }

    private function generateReturnNumber(): string
    {
        $last = CustomerReturn::withTrashed()->lockForUpdate()->pluck('return_number')->map(
            fn ($number) => preg_match('/^DEV-(\d{6,})$/', (string) $number, $matches) ? (int) $matches[1] : 0
        )->max() ?? 0;
        do { $number = 'DEV-'.str_pad((string) ++$last, 6, '0', STR_PAD_LEFT); }
        while (CustomerReturn::withTrashed()->where('return_number', $number)->exists());
        return $number;
    }

    private function isRetriableCollision(UniqueConstraintViolationException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());
        return str_contains($message, 'customer_returns_return_number_unique')
            || str_contains($message, 'customer_returns.return_number')
            || str_contains($message, 'customer_returns_idempotency_key_unique')
            || str_contains($message, 'customer_returns.idempotency_key');
    }
}

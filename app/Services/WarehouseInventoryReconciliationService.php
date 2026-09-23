<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItem;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseInventoryReconciliationService
{
    private const QUANTITY_SCALE = 4;

    private const UNIT_COST_SCALE = 6;

    private const TOTAL_COST_SCALE = 2;

    private const QUANTITY_TOLERANCE = 1;

    private const VALUE_TOLERANCE = 1;

    private const AVERAGE_TOLERANCE = 1;

    /**
     * Auditoría de solo lectura. No normaliza ni modifica ninguna fuente.
     */
    public function audit(int $companyId, ?int $warehouseId = null, ?int $articleId = null): array
    {
        $stocks = WarehouseStock::query()
            ->where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->when($warehouseId, fn ($query, $id) => $query->where('warehouse_id', $id))
            ->when($articleId, fn ($query, $id) => $query->where('article_id', $id))
            ->get();
        $pools = WarehouseValuationPool::query()
            ->where('company_id', $companyId)
            ->when($warehouseId, fn ($query, $id) => $query->where('warehouse_id', $id))
            ->when($articleId, fn ($query, $id) => $query->where('article_id', $id))
            ->get();
        $movements = WarehouseKardexMovement::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->when($warehouseId, fn ($query, $id) => $query->where('warehouse_id', $id))
            ->when($articleId, fn ($query, $id) => $query->where('article_id', $id))
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $groups = [];
        $issuesByGroup = [];
        $ensureGroup = function (int $warehouse, int $article) use (&$groups, $companyId): string {
            $key = $this->groupKey($companyId, $warehouse, $article);
            $groups[$key] ??= [
                'company_id' => $companyId,
                'warehouse_id' => $warehouse,
                'article_id' => $article,
            ];

            return $key;
        };
        $addIssue = function (string $key, string $category, string $type, string $severity, array $data = []) use (&$issuesByGroup, &$groups): void {
            $identity = $groups[$key];
            $issuesByGroup[$key][] = array_merge([
                'category' => $category,
                'type' => $type,
                'severity' => $severity,
                'company_id' => $identity['company_id'],
                'warehouse_id' => $identity['warehouse_id'],
                'article_id' => $identity['article_id'],
                'movement_id' => null,
                'movement_number' => null,
                'source_type' => null,
                'source_id' => null,
                'source_key' => null,
                'expected' => null,
                'actual' => null,
                'difference' => null,
                'message' => '',
            ], $data);
        };

        foreach ($stocks as $stock) {
            $ensureGroup((int) $stock->warehouse_id, (int) $stock->article_id);
        }
        foreach ($pools as $pool) {
            $ensureGroup((int) $pool->warehouse_id, (int) $pool->article_id);
        }
        foreach ($movements as $movement) {
            $ensureGroup((int) $movement->warehouse_id, (int) $movement->article_id);
        }

        $this->auditDocumentOrigins(
            $companyId,
            $warehouseId,
            $articleId,
            $movements,
            $ensureGroup,
            $addIssue
        );
        $this->auditMovementOrigins($movements, $ensureGroup, $addIssue);
        $this->auditAdjustments($movements, $ensureGroup, $addIssue);
        $this->auditDuplicates($movements, $ensureGroup, $addIssue);

        foreach ($movements as $movement) {
            if ($this->hasIncompleteSnapshots($movement)) {
                $key = $ensureGroup((int) $movement->warehouse_id, (int) $movement->article_id);
                $addIssue($key, 'snapshot', 'incomplete_snapshot', 'warning', [
                    'movement_id' => $movement->id,
                    'movement_number' => $movement->movement_number,
                    'source_type' => $movement->source_type,
                    'source_id' => $movement->source_id,
                    'source_key' => $movement->source_key,
                    'expected' => 'Snapshots SUNAT/contables completos',
                    'actual' => 'Uno o más snapshots ausentes',
                    'message' => 'El movimiento tiene snapshots requeridos incompletos y debe revisarse en el backfill.',
                ]);
            }
        }

        $stockByGroup = $stocks->groupBy(fn ($row) => $this->groupKey($companyId, (int) $row->warehouse_id, (int) $row->article_id));
        $poolByGroup = $pools->keyBy(fn ($row) => $this->groupKey($companyId, (int) $row->warehouse_id, (int) $row->article_id));
        $movementByGroup = $movements->groupBy(fn ($row) => $this->groupKey($companyId, (int) $row->warehouse_id, (int) $row->article_id));
        $warehouseNames = DB::table('warehouses')->whereIn('id', collect($groups)->pluck('warehouse_id')->unique())->pluck('name', 'id');
        $articles = DB::table('articles')
            ->whereIn('id', collect($groups)->pluck('article_id')->unique())
            ->get(['id', 'code', 'billing_name', 'item_kind', 'is_inventory_item'])
            ->keyBy('id');

        foreach ($stocks as $stock) {
            $key = $this->groupKey($companyId, (int) $stock->warehouse_id, (int) $stock->article_id);
            if ($this->decimalToUnits($stock->current_quantity, self::QUANTITY_SCALE) !== 0
                && ($movementByGroup->get($key)?->isEmpty() ?? true)) {
                $addIssue($key, 'orphan', 'stock_without_kardex', 'error', [
                    'expected' => 'Historial Kardex para stock material',
                    'actual' => 'Sin movimientos efectivos',
                    'difference' => $this->normalizeDecimal($stock->current_quantity, self::QUANTITY_SCALE),
                    'message' => 'Existe stock físico distinto de cero sin correspondencia posible en Kardex.',
                ]);
            }
        }

        foreach ($pools as $pool) {
            $key = $this->groupKey($companyId, (int) $pool->warehouse_id, (int) $pool->article_id);
            $hasMaterialPool = $this->decimalToUnits($pool->current_quantity, self::QUANTITY_SCALE) !== 0
                || $this->decimalToUnits($pool->total_cost, self::TOTAL_COST_SCALE) !== 0;
            if ($hasMaterialPool && ($movementByGroup->get($key)?->isEmpty() ?? true)) {
                $addIssue($key, 'orphan', 'pool_without_kardex', 'error', [
                    'expected' => 'Historial Kardex para pool material',
                    'actual' => 'Sin movimientos efectivos',
                    'message' => 'El pool tiene cantidad o valor material sin historial Kardex.',
                ]);
            }
            if (! $articles->has((int) $pool->article_id) || ! $warehouseNames->has((int) $pool->warehouse_id)) {
                $addIssue($key, 'orphan', 'pool_invalid_dimension', 'error', [
                    'expected' => 'Artículo y almacén válidos',
                    'actual' => 'Dimensión inexistente',
                    'message' => 'El pool referencia un artículo o almacén inexistente.',
                ]);
            }
        }

        $results = [];
        foreach ($groups as $key => $identity) {
            $groupStocks = $stockByGroup->get($key, collect());
            $groupMovements = $movementByGroup->get($key, collect());
            $pool = $poolByGroup->get($key);

            $physicalQuantityUnits = $this->sumUnits($groupStocks, 'current_quantity', self::QUANTITY_SCALE);
            $kardexQuantityUnits = $this->sumUnits($groupMovements, 'quantity_in', self::QUANTITY_SCALE)
                - $this->sumUnits($groupMovements, 'quantity_out', self::QUANTITY_SCALE);
            $kardexCostUnits = $this->sumUnits($groupMovements, 'total_cost_in', self::TOTAL_COST_SCALE)
                - $this->sumUnits($groupMovements, 'total_cost_out', self::TOTAL_COST_SCALE);
            $poolQuantityUnits = $pool ? $this->decimalToUnits($pool->current_quantity, self::QUANTITY_SCALE) : 0;
            $poolCostUnits = $pool ? $this->decimalToUnits($pool->total_cost, self::TOTAL_COST_SCALE) : 0;
            $poolAverageUnits = $pool ? $this->decimalToUnits($pool->average_unit_cost, self::UNIT_COST_SCALE) : 0;
            $kardexAverageUnits = $this->averageCostUnits($kardexQuantityUnits, $kardexCostUnits);

            $stockDifference = $physicalQuantityUnits - $kardexQuantityUnits;
            $poolQuantityDifference = $poolQuantityUnits - $kardexQuantityUnits;
            $valueDifference = $poolCostUnits - $kardexCostUnits;
            $averageDifference = $poolAverageUnits - $kardexAverageUnits;

            if (abs($stockDifference) > self::QUANTITY_TOLERANCE) {
                $addIssue($key, 'reconciliation', 'stock_quantity_mismatch', 'error', [
                    'expected' => $this->unitsToDecimal($kardexQuantityUnits, self::QUANTITY_SCALE),
                    'actual' => $this->unitsToDecimal($physicalQuantityUnits, self::QUANTITY_SCALE),
                    'difference' => $this->unitsToDecimal($stockDifference, self::QUANTITY_SCALE),
                    'message' => 'El stock físico no coincide con el saldo físico del Kardex.',
                ]);
            }
            if (abs($poolQuantityDifference) > self::QUANTITY_TOLERANCE) {
                $addIssue($key, 'reconciliation', 'pool_quantity_mismatch', 'error', [
                    'expected' => $this->unitsToDecimal($kardexQuantityUnits, self::QUANTITY_SCALE),
                    'actual' => $this->unitsToDecimal($poolQuantityUnits, self::QUANTITY_SCALE),
                    'difference' => $this->unitsToDecimal($poolQuantityDifference, self::QUANTITY_SCALE),
                    'message' => 'La cantidad del pool no coincide con el saldo físico del Kardex.',
                ]);
            }
            if (abs($valueDifference) > self::VALUE_TOLERANCE) {
                $addIssue($key, 'reconciliation', 'pool_value_mismatch', 'error', [
                    'expected' => $this->unitsToDecimal($kardexCostUnits, self::TOTAL_COST_SCALE),
                    'actual' => $this->unitsToDecimal($poolCostUnits, self::TOTAL_COST_SCALE),
                    'difference' => $this->unitsToDecimal($valueDifference, self::TOTAL_COST_SCALE),
                    'message' => 'El valor del pool no coincide con el saldo valorizado del Kardex.',
                ]);
            }
            if (abs($averageDifference) > self::AVERAGE_TOLERANCE) {
                $addIssue($key, 'reconciliation', 'pool_average_cost_mismatch', 'error', [
                    'expected' => $this->unitsToDecimal($kardexAverageUnits, self::UNIT_COST_SCALE),
                    'actual' => $this->unitsToDecimal($poolAverageUnits, self::UNIT_COST_SCALE),
                    'difference' => $this->unitsToDecimal($averageDifference, self::UNIT_COST_SCALE),
                    'message' => 'El PPM del pool no coincide con el PPM derivado del Kardex.',
                ]);
            }

            $article = $articles->get($identity['article_id']);
            $isInventoryArticle = $article
                && (bool) $article->is_inventory_item
                && strtolower((string) $article->item_kind) !== 'service';
            if (! $isInventoryArticle
                && ($physicalQuantityUnits !== 0 || $poolQuantityUnits !== 0 || $kardexQuantityUnits !== 0 || $kardexCostUnits !== 0)) {
                $addIssue($key, 'document', 'non_inventory_item_with_inventory', 'error', [
                    'expected' => 'Sin stock, pool ni movimiento físico/valorizado',
                    'actual' => 'Tiene impacto de inventario',
                    'message' => 'Un servicio o artículo no inventariable está afectando inventario.',
                ]);
            }

            $groupIssues = collect($issuesByGroup[$key] ?? []);
            $hasError = $groupIssues->contains(fn (array $issue) => $issue['severity'] === 'error');
            $hasWarning = $groupIssues->contains(fn (array $issue) => $issue['severity'] === 'warning');
            $snapshotMovement = $groupMovements->last();

            $results[] = array_merge($identity, [
                'warehouse_name' => $warehouseNames->get($identity['warehouse_id']),
                'article_code' => $snapshotMovement?->article_code_snapshot,
                'article_description' => $snapshotMovement?->article_description_snapshot,
                'physical_quantity' => $this->unitsToDecimal($physicalQuantityUnits, self::QUANTITY_SCALE),
                'kardex_quantity' => $this->unitsToDecimal($kardexQuantityUnits, self::QUANTITY_SCALE),
                'pool_quantity' => $this->unitsToDecimal($poolQuantityUnits, self::QUANTITY_SCALE),
                'kardex_total_cost' => $this->unitsToDecimal($kardexCostUnits, self::TOTAL_COST_SCALE),
                'pool_total_cost' => $this->unitsToDecimal($poolCostUnits, self::TOTAL_COST_SCALE),
                'kardex_average_cost' => $this->unitsToDecimal($kardexAverageUnits, self::UNIT_COST_SCALE),
                'pool_average_cost' => $this->unitsToDecimal($poolAverageUnits, self::UNIT_COST_SCALE),
                'quantity_difference_stock_vs_kardex' => $this->unitsToDecimal($stockDifference, self::QUANTITY_SCALE),
                'quantity_difference_pool_vs_kardex' => $this->unitsToDecimal($poolQuantityDifference, self::QUANTITY_SCALE),
                'value_difference_pool_vs_kardex' => $this->unitsToDecimal($valueDifference, self::TOTAL_COST_SCALE),
                'average_cost_difference_pool_vs_kardex' => $this->unitsToDecimal($averageDifference, self::UNIT_COST_SCALE),
                'document_issues_count' => $groupIssues->where('category', 'document')->count(),
                'duplicate_issues_count' => $groupIssues->where('category', 'duplicate')->count(),
                'orphan_issues_count' => $groupIssues->where('category', 'orphan')->count(),
                'snapshot_issues_count' => $groupIssues->where('category', 'snapshot')->count(),
                'status' => $hasError ? 'ERROR' : ($hasWarning ? 'WARNING' : 'OK'),
                'issues' => $groupIssues->values()->all(),
            ]);
        }

        $resultCollection = collect($results);
        $allIssues = $resultCollection->pluck('issues')->flatten(1);

        return [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'article_id' => $articleId,
            'groups' => $resultCollection->sortBy(['warehouse_id', 'article_id'])->values()->all(),
            'issues' => $allIssues->values()->all(),
            'summary' => [
                'total_groups' => $resultCollection->count(),
                'ok_groups' => $resultCollection->where('status', 'OK')->count(),
                'warning_groups' => $resultCollection->where('status', 'WARNING')->count(),
                'error_groups' => $resultCollection->where('status', 'ERROR')->count(),
                'stock_quantity_mismatches' => $resultCollection->filter(fn ($group) => abs($this->decimalToUnits($group['quantity_difference_stock_vs_kardex'], self::QUANTITY_SCALE)) > self::QUANTITY_TOLERANCE)->count(),
                'pool_quantity_mismatches' => $resultCollection->filter(fn ($group) => abs($this->decimalToUnits($group['quantity_difference_pool_vs_kardex'], self::QUANTITY_SCALE)) > self::QUANTITY_TOLERANCE)->count(),
                'valuation_mismatches' => $resultCollection->filter(fn ($group) => abs($this->decimalToUnits($group['value_difference_pool_vs_kardex'], self::TOTAL_COST_SCALE)) > self::VALUE_TOLERANCE)->count(),
                'document_issues' => $allIssues->where('category', 'document')->count(),
                'duplicate_issues' => $allIssues->where('category', 'duplicate')->count(),
                'orphan_issues' => $allIssues->where('category', 'orphan')->count(),
                'snapshot_issues' => $allIssues->where('category', 'snapshot')->count(),
            ],
        ];
    }

    private function auditDocumentOrigins(
        int $companyId,
        ?int $warehouseId,
        ?int $articleId,
        Collection $movements,
        callable $ensureGroup,
        callable $addIssue
    ): void {
        $inventoryRows = fn ($query) => $query
            ->where('articles.is_inventory_item', true)
            ->where('articles.item_kind', '!=', 'service');

        $entryQuery = DB::table('warehouse_entry_items as item')
            ->join('warehouse_entries as source', 'source.id', '=', 'item.warehouse_entry_id')
            ->join('articles', 'articles.id', '=', 'item.article_id')
            ->where('source.company_id', $companyId)
            ->where('source.status', 'registered')
            ->where('item.status', '!=', 'deleted')
            ->whereNull('source.deleted_at');
        $inventoryRows($entryQuery);
        $this->applyOriginFilters($entryQuery, $warehouseId, $articleId, 'source.warehouse_id', 'item.article_id');
        foreach ($entryQuery->get(['source.id as source_id', 'source.warehouse_id', 'item.id as item_id', 'item.article_id']) as $item) {
            $key = $ensureGroup((int) $item->warehouse_id, (int) $item->article_id);
            $count = $this->baseMovements($movements, WarehouseEntry::class, (int) $item->source_id, WarehouseEntryItem::class, (int) $item->item_id, 'warehouse_entry')->count();
            if ($count === 0) {
                $addIssue($key, 'document', 'warehouse_entry_without_movement', 'error', [
                    'source_type' => WarehouseEntry::class, 'source_id' => $item->source_id,
                    'expected' => 'Al menos un movimiento de entrada', 'actual' => '0',
                    'message' => 'Una entrada registrada no tiene movimiento Kardex para el ítem inventariable.',
                ]);
            }
        }

        $dispatchQuery = DB::table('warehouse_dispatch_items as item')
            ->join('warehouse_dispatches as source', 'source.id', '=', 'item.warehouse_dispatch_id')
            ->join('articles', 'articles.id', '=', 'item.article_id')
            ->where('source.company_id', $companyId);
        $inventoryRows($dispatchQuery);
        $this->applyOriginFilters($dispatchQuery, $warehouseId, $articleId, 'source.warehouse_id', 'item.article_id');
        foreach ($dispatchQuery->get(['source.id as source_id', 'source.status as source_status', 'source.warehouse_id', 'item.id as item_id', 'item.article_id']) as $item) {
            $key = $ensureGroup((int) $item->warehouse_id, (int) $item->article_id);
            $base = $this->baseMovements($movements, WarehouseDispatch::class, (int) $item->source_id, WarehouseDispatchItem::class, (int) $item->item_id, 'customer_order_dispatch');
            if ($item->source_status === WarehouseDispatch::STATUS_CONFIRMED && $base->count() !== 1) {
                $type = $base->isEmpty() ? 'confirmed_dispatch_without_exit' : 'duplicate_dispatch_exit';
                $category = $base->isEmpty() ? 'document' : 'duplicate';
                $addIssue($key, $category, $type, 'error', [
                    'source_type' => WarehouseDispatch::class, 'source_id' => $item->source_id,
                    'expected' => '1 salida', 'actual' => (string) $base->count(),
                    'message' => $base->isEmpty()
                        ? 'El despacho confirmado no tiene su salida Kardex.'
                        : 'El despacho confirmado tiene más de una salida para el mismo ítem.',
                ]);
            }
            if ($item->source_status === WarehouseDispatch::STATUS_DRAFT && $base->isNotEmpty()) {
                $addIssue($key, 'document', 'draft_dispatch_with_exit', 'error', [
                    'source_type' => WarehouseDispatch::class, 'source_id' => $item->source_id,
                    'expected' => '0 salidas', 'actual' => (string) $base->count(),
                    'message' => 'Un despacho en borrador tiene salida física en Kardex.',
                ]);
            }
        }

        $invoiceQuery = DB::table('electronic_invoice_items as item')
            ->join('electronic_invoices as source', 'source.id', '=', 'item.electronic_invoice_id')
            ->leftJoin('warehouse_entries as entry', 'entry.id', '=', 'source.warehouse_entry_id')
            ->join('articles', 'articles.id', '=', 'item.article_id')
            ->where('source.company_id', $companyId)
            ->where('source.status', ElectronicInvoice::STATUS_GENERATED)
            ->where('source.is_voided', false)
            ->whereNull('source.deleted_at');
        $inventoryRows($invoiceQuery);
        if ($warehouseId) {
            $invoiceQuery->whereRaw('COALESCE(source.warehouse_id, entry.warehouse_id) = ?', [$warehouseId]);
        }
        if ($articleId) {
            $invoiceQuery->where('item.article_id', $articleId);
        }
        foreach ($invoiceQuery->get([
            'source.id as source_id', 'item.id as item_id', 'item.article_id',
            DB::raw('COALESCE(source.warehouse_id, entry.warehouse_id) as warehouse_id'),
        ]) as $item) {
            if (! $item->warehouse_id) {
                continue;
            }
            $key = $ensureGroup((int) $item->warehouse_id, (int) $item->article_id);
            $base = $this->baseMovements($movements, ElectronicInvoice::class, (int) $item->source_id, ElectronicInvoiceItem::class, (int) $item->item_id, 'electronic_invoice');
            $isDispatchBacked = DB::table('electronic_invoice_item_dispatch_allocations')
                ->where('electronic_invoice_item_id', $item->item_id)
                ->whereNull('deleted_at')
                ->exists();
            if ($isDispatchBacked && $base->isNotEmpty()) {
                $addIssue($key, 'duplicate', 'dispatch_backed_invoice_with_exit', 'error', [
                    'source_type' => ElectronicInvoice::class, 'source_id' => $item->source_id,
                    'expected' => '0 salidas adicionales', 'actual' => (string) $base->count(),
                    'message' => 'Una factura respaldada por despacho generó una segunda salida.',
                ]);
            }
            if (! $isDispatchBacked && $base->isEmpty()) {
                $addIssue($key, 'document', 'direct_invoice_without_exit', 'error', [
                    'source_type' => ElectronicInvoice::class, 'source_id' => $item->source_id,
                    'expected' => 'Al menos una salida', 'actual' => '0',
                    'message' => 'Una factura directa inventariable no tiene salida Kardex.',
                ]);
            }
        }

        $returnQuery = DB::table('customer_return_items as item')
            ->join('customer_returns as source', 'source.id', '=', 'item.customer_return_id')
            ->join('articles', 'articles.id', '=', 'item.article_id')
            ->where('source.company_id', $companyId)
            ->whereNull('source.deleted_at');
        $inventoryRows($returnQuery);
        $this->applyOriginFilters($returnQuery, $warehouseId, $articleId, 'source.warehouse_id', 'item.article_id');
        foreach ($returnQuery->get([
            'source.id as source_id', 'source.status as source_status', 'source.warehouse_id',
            'item.id as item_id', 'item.article_id', 'item.warehouse_dispatch_item_id',
            'item.quantity', 'item.unit_cost_snapshot', 'item.total_cost',
        ]) as $item) {
            $key = $ensureGroup((int) $item->warehouse_id, (int) $item->article_id);
            $base = $this->baseMovements($movements, CustomerReturn::class, (int) $item->source_id, CustomerReturnItem::class, (int) $item->item_id, 'customer_return');
            $reversals = $this->baseMovements($movements, CustomerReturn::class, (int) $item->source_id, CustomerReturnItem::class, (int) $item->item_id, 'customer_return_reversal');
            if (in_array($item->source_status, [CustomerReturn::STATUS_CONFIRMED, CustomerReturn::STATUS_REVERSED], true) && $base->count() !== 1) {
                $addIssue($key, $base->count() > 1 ? 'duplicate' : 'document', $base->count() > 1 ? 'duplicate_customer_return' : 'customer_return_without_entry', 'error', [
                    'source_type' => CustomerReturn::class, 'source_id' => $item->source_id,
                    'expected' => '1 reingreso', 'actual' => (string) $base->count(),
                    'message' => $base->count() > 1 ? 'La devolución tiene más de un reingreso.' : 'La devolución no tiene movimiento de reingreso.',
                ]);
            }
            if ($item->source_status === CustomerReturn::STATUS_REVERSED && $reversals->count() !== 1) {
                $addIssue($key, $reversals->count() > 1 ? 'duplicate' : 'document', $reversals->count() > 1 ? 'duplicate_customer_return_reversal' : 'customer_return_reversal_missing', 'error', [
                    'source_type' => CustomerReturn::class, 'source_id' => $item->source_id,
                    'expected' => '1 reversa', 'actual' => (string) $reversals->count(),
                    'message' => $reversals->count() > 1 ? 'La devolución tiene doble reversa.' : 'La devolución revertida no tiene movimiento inverso.',
                ]);
            }
            if ($base->count() === 1) {
                $movement = $base->first();
                $expectedQuantity = $this->decimalToUnits($item->quantity, self::QUANTITY_SCALE);
                $expectedCost = $this->decimalToUnits($item->total_cost, self::TOTAL_COST_SCALE);
                if ($this->decimalToUnits($movement->quantity_in, self::QUANTITY_SCALE) !== $expectedQuantity
                    || $this->decimalToUnits($movement->total_cost_in, self::TOTAL_COST_SCALE) !== $expectedCost) {
                    $addIssue($key, 'document', 'customer_return_value_mismatch', 'error', [
                        'movement_id' => $movement->id, 'movement_number' => $movement->movement_number,
                        'source_type' => CustomerReturn::class, 'source_id' => $item->source_id,
                        'expected' => $this->unitsToDecimal($expectedQuantity, self::QUANTITY_SCALE).' / '.$this->unitsToDecimal($expectedCost, self::TOTAL_COST_SCALE),
                        'actual' => $movement->quantity_in.' / '.$movement->total_cost_in,
                        'message' => 'Cantidad o costo histórico de la devolución no coincide con su ítem.',
                    ]);
                }
                $dispatchMovementId = DB::table('warehouse_dispatch_items')->where('id', $item->warehouse_dispatch_item_id)->value('kardex_movement_id');
                if (! $dispatchMovementId || ! WarehouseKardexMovement::query()->whereKey($dispatchMovementId)->exists()) {
                    $addIssue($key, 'document', 'customer_return_without_original_exit', 'error', [
                        'movement_id' => $movement->id, 'movement_number' => $movement->movement_number,
                        'source_type' => CustomerReturn::class, 'source_id' => $item->source_id,
                        'expected' => 'SAL original vinculada', 'actual' => 'No encontrada',
                        'message' => 'La devolución no conserva vínculo verificable con la salida original.',
                    ]);
                }
            }
        }
    }

    private function auditMovementOrigins(Collection $movements, callable $ensureGroup, callable $addIssue): void
    {
        $sourceTables = [
            WarehouseEntry::class => ['warehouse_entries', true],
            WarehouseDispatch::class => ['warehouse_dispatches', true],
            ElectronicInvoice::class => ['electronic_invoices', true],
            CustomerReturn::class => ['customer_returns', true],
        ];
        $itemTables = [
            WarehouseEntryItem::class => 'warehouse_entry_items',
            WarehouseDispatchItem::class => 'warehouse_dispatch_items',
            ElectronicInvoiceItem::class => 'electronic_invoice_items',
            CustomerReturnItem::class => 'customer_return_items',
            'App\\Models\\WarehouseEntryExpenseDistribution' => 'warehouse_entry_expense_distributions',
        ];

        foreach ($movements as $movement) {
            $key = $ensureGroup((int) $movement->warehouse_id, (int) $movement->article_id);
            if (! $movement->source_type || ! $movement->source_id) {
                if ($movement->operation_type !== 'manual_adjustment') {
                    $addIssue($key, 'orphan', 'movement_without_origin', 'error', $this->movementIssueData($movement, [
                        'expected' => 'Origen identificable', 'actual' => 'Sin source_type/source_id',
                        'message' => 'El movimiento no tiene origen identificable.',
                    ]));
                }
                continue;
            }

            if (! isset($sourceTables[$movement->source_type])) {
                $addIssue($key, 'orphan', 'movement_unknown_origin_type', 'error', $this->movementIssueData($movement, [
                    'expected' => 'Tipo de origen reconocido', 'actual' => $movement->source_type,
                    'message' => 'El movimiento usa un tipo de origen no reconocido por la auditoría.',
                ]));
                continue;
            }

            [$table, $hasCompany] = $sourceTables[$movement->source_type];
            $sourceQuery = DB::table($table)->where('id', $movement->source_id);
            if ($hasCompany) {
                $sourceQuery->where('company_id', $movement->company_id);
            }
            if (! $sourceQuery->exists()) {
                $addIssue($key, 'orphan', 'movement_origin_missing', 'error', $this->movementIssueData($movement, [
                    'expected' => 'Origen existente en la misma empresa', 'actual' => 'No encontrado',
                    'message' => 'El source_type/source_id del movimiento apunta a un origen inexistente.',
                ]));
            }

            if ($movement->source_item_type || $movement->source_item_id) {
                $itemTable = $itemTables[$movement->source_item_type] ?? null;
                if (! $itemTable || ! $movement->source_item_id || ! DB::table($itemTable)->where('id', $movement->source_item_id)->exists()) {
                    $addIssue($key, 'orphan', 'movement_source_item_missing', 'error', $this->movementIssueData($movement, [
                        'expected' => 'Ítem de origen existente', 'actual' => 'No encontrado',
                        'message' => 'El source_item_type/source_item_id del movimiento no puede validarse.',
                    ]));
                }
            }
        }
    }

    private function auditDuplicates(Collection $movements, callable $ensureGroup, callable $addIssue): void
    {
        $registered = $movements->where('status', 'registered');
        foreach ($registered->filter(fn ($movement) => filled($movement->source_key))->groupBy('source_key') as $sourceKey => $duplicates) {
            if ($duplicates->count() <= 1) {
                continue;
            }
            $movement = $duplicates->first();
            $key = $ensureGroup((int) $movement->warehouse_id, (int) $movement->article_id);
            $addIssue($key, 'duplicate', 'duplicate_source_key', 'error', $this->movementIssueData($movement, [
                'expected' => '1 movimiento por source_key', 'actual' => (string) $duplicates->count(),
                'difference' => (string) ($duplicates->count() - 1),
                'message' => 'Existen movimientos registrados con el mismo source_key lógico.',
            ]));
        }

        $baseOperations = ['warehouse_entry', 'customer_order_dispatch', 'electronic_invoice', 'customer_return'];
        $equivalents = $registered
            ->filter(fn ($movement) => in_array($movement->operation_type, $baseOperations, true)
                && ($this->decimalToUnits($movement->quantity_in, self::QUANTITY_SCALE) !== 0
                    || $this->decimalToUnits($movement->quantity_out, self::QUANTITY_SCALE) !== 0))
            ->groupBy(fn ($movement) => implode('|', [
                $movement->source_type,
                $movement->source_id,
                $movement->source_item_type,
                $movement->source_item_id,
                $movement->warehouse_stock_id,
                $movement->operation_type,
            ]));
        foreach ($equivalents as $duplicates) {
            if ($duplicates->count() <= 1) {
                continue;
            }
            $movement = $duplicates->first();
            $key = $ensureGroup((int) $movement->warehouse_id, (int) $movement->article_id);
            $addIssue($key, 'duplicate', 'duplicate_origin_movement', 'error', $this->movementIssueData($movement, [
                'expected' => '1 movimiento base por origen, ítem y stock', 'actual' => (string) $duplicates->count(),
                'difference' => (string) ($duplicates->count() - 1),
                'message' => 'Existe más de un movimiento físico equivalente para el mismo origen, ítem y stock.',
            ]));
        }
    }

    private function auditAdjustments(Collection $movements, callable $ensureGroup, callable $addIssue): void
    {
        foreach ($movements->where('operation_type', 'manual_adjustment') as $movement) {
            $quantityIn = $this->decimalToUnits($movement->quantity_in, self::QUANTITY_SCALE);
            $quantityOut = $this->decimalToUnits($movement->quantity_out, self::QUANTITY_SCALE);
            $isValidIn = $movement->movement_type === 'adjustment_in' && $quantityIn > 0 && $quantityOut === 0;
            $isValidOut = $movement->movement_type === 'adjustment_out' && $quantityOut > 0 && $quantityIn === 0;

            if (! $isValidIn && ! $isValidOut) {
                $key = $ensureGroup((int) $movement->warehouse_id, (int) $movement->article_id);
                $addIssue($key, 'document', 'invalid_manual_adjustment_direction', 'error', $this->movementIssueData($movement, [
                    'expected' => 'Ajuste de entrada o salida con una sola dirección física',
                    'actual' => $movement->movement_type.' / '.$movement->quantity_in.' / '.$movement->quantity_out,
                    'message' => 'El ajuste manual no es coherente con su tipo y cantidades.',
                ]));
            }
        }
    }

    private function baseMovements(
        Collection $movements,
        string $sourceType,
        int $sourceId,
        string $sourceItemType,
        int $sourceItemId,
        string $operationType
    ): Collection {
        return $movements->filter(fn ($movement) => $movement->source_type === $sourceType
            && (int) $movement->source_id === $sourceId
            && $movement->source_item_type === $sourceItemType
            && (int) $movement->source_item_id === $sourceItemId
            && $movement->operation_type === $operationType
            && $movement->status === 'registered');
    }

    private function applyOriginFilters($query, ?int $warehouseId, ?int $articleId, string $warehouseColumn, string $articleColumn): void
    {
        if ($warehouseId) {
            $query->where($warehouseColumn, $warehouseId);
        }
        if ($articleId) {
            $query->where($articleColumn, $articleId);
        }
    }

    private function movementIssueData(WarehouseKardexMovement $movement, array $data): array
    {
        return array_merge([
            'movement_id' => $movement->id,
            'movement_number' => $movement->movement_number,
            'source_type' => $movement->source_type,
            'source_id' => $movement->source_id,
            'source_key' => $movement->source_key,
        ], $data);
    }

    private function hasIncompleteSnapshots(WarehouseKardexMovement $movement): bool
    {
        foreach ([
            $movement->sunat_establishment_code_snapshot,
            $movement->article_code_snapshot,
            $movement->article_description_snapshot,
            $movement->sunat_existence_type_code_snapshot,
            $movement->sunat_unit_code_snapshot,
            $movement->valuation_method_code_snapshot,
            $movement->valuation_method_description_snapshot,
            $movement->sunat_operation_type_code_snapshot,
        ] as $snapshot) {
            if (blank($snapshot)) {
                return true;
            }
        }

        $hasExternalDocument = filled($movement->document_type)
            && ! in_array($movement->document_type, ['SIN_COMPROBANTE', 'RECIBO_INTERNO'], true);

        return $hasExternalDocument && (
            blank($movement->document_date_snapshot)
            || blank($movement->sunat_document_type_code_snapshot)
            || blank($movement->document_number)
        );
    }

    private function averageCostUnits(int $quantityUnits, int $costUnits): int
    {
        if ($quantityUnits <= 0) {
            return 0;
        }

        $quantity = $quantityUnits / (10 ** self::QUANTITY_SCALE);
        $cost = $costUnits / (10 ** self::TOTAL_COST_SCALE);

        return (int) round(($cost / $quantity) * (10 ** self::UNIT_COST_SCALE), 0, PHP_ROUND_HALF_UP);
    }

    private function sumUnits(iterable $rows, string $field, int $scale): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += $this->decimalToUnits($row->{$field}, $scale);
        }

        return $sum;
    }

    private function normalizeDecimal(mixed $value, int $scale): string
    {
        return $this->unitsToDecimal($this->decimalToUnits($value, $scale), $scale);
    }

    private function decimalToUnits(mixed $value, int $scale): int
    {
        $decimal = trim((string) ($value ?? '0'));
        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '+-');
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $units = ((int) ($whole === '' ? '0' : $whole) * (10 ** $scale)) + (int) $fraction;

        return $negative ? -$units : $units;
    }

    private function unitsToDecimal(int $units, int $scale): string
    {
        $factor = 10 ** $scale;
        $sign = $units < 0 ? '-' : '';
        $absolute = abs($units);

        return $sign.intdiv($absolute, $factor).'.'.str_pad((string) ($absolute % $factor), $scale, '0', STR_PAD_LEFT);
    }

    private function groupKey(int $companyId, int $warehouseId, int $articleId): string
    {
        return $companyId.'|'.$warehouseId.'|'.$articleId;
    }
}

<?php

namespace App\Services;

use App\Models\CustomerPurchaseOrder;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItemDispatchAllocation;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseKardexMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InvoiceFromCustomerOrderService
{
    private const QUANTITY_TOLERANCE = 0.00001;

    public function __construct(
        private readonly ArticleInventoryPolicy $articleInventoryPolicy,
        private readonly SunatUnitPolicy $sunatUnitPolicy
    ) {}

    public function prepare(CustomerPurchaseOrder $order): array
    {
        $order->loadMissing([
            'customer', 'customerBranch', 'currency',
            'items' => fn ($query) => $query->where('status', '!=', 'deleted')
                ->with(['article.unit.sunatUnit.catalog', 'unit', 'presentation', 'brand']),
            'electronicInvoices.items', 'electronicInvoices.collections',
        ]);
        $dispatchBacked = $this->shouldUseDispatchBacking($order);
        $items = $dispatchBacked
            ? array_merge($this->dispatchBackedItems($order), $this->commercialPendingItems($order))
            : $this->legacyPendingItems($order);

        return [
            'id' => $order->id,
            'company_id' => $order->company_id,
            'customer_id' => $order->customer_id,
            'customer_branch_id' => $order->customer_branch_id,
            'quote_id' => $order->quote_id,
            'currency_id' => $order->currency_id,
            'purchase_order_number' => $order->purchase_order_number ?: $order->code,
            'siaf_number' => $order->siaf_file_number,
            'process_number' => $order->process_type,
            'dispatch_backed' => $dispatchBacked,
            'warehouse_context' => $dispatchBacked ? $this->dispatchWarehouseContext($items) : null,
            'summary' => $this->summary($order),
            'items' => $items,
        ];
    }

    public function validateGeneratedInvoice(
        CustomerPurchaseOrder $order,
        array $items,
        ?ElectronicInvoice $currentInvoice = null,
        ?float $invoiceTotal = null,
        ?bool $useDispatchBacking = null
    ): void {
        $order = CustomerPurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
        if (in_array($order->status, ['cancelled', 'not_attended'], true)) {
            throw ValidationException::withMessages(['customer_purchase_order_id' => 'No se puede facturar una orden anulada o no atendida.']);
        }

        $useDispatchBacking ??= $this->shouldUseDispatchBacking($order, $currentInvoice);
        if ($useDispatchBacking) {
            $this->validateDispatchBackedItems($order, $items, $currentInvoice);
        } else {
            $this->validateLegacyItems($order, $items, $currentInvoice);
        }

        if ($invoiceTotal !== null) {
            $alreadyBilled = ElectronicInvoice::query()
                ->where('customer_purchase_order_id', $order->id)
                ->whereNotIn('status', ['draft', 'cancelled', 'voided'])
                ->where('is_voided', false)
                ->when($currentInvoice, fn ($query) => $query->whereKeyNot($currentInvoice->id))
                ->sum('total_amount');
            $remainingAmount = max(0, (float) $order->grand_total - (float) $alreadyBilled);
            if ($invoiceTotal > $remainingAmount + self::QUANTITY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'items' => "El total de la factura supera el saldo por facturar de la orden ({$remainingAmount}).",
                ]);
            }
        }
    }

    public function allocateDispatchesForGeneratedInvoice(
        CustomerPurchaseOrder $order,
        ElectronicInvoice $invoice,
        array $requestedItems
    ): void {
        $this->validateDispatchBackedItems($order, $requestedItems, $invoice);
        $dispatchItems = $this->confirmedDispatchItemsQuery($order)
            ->with(['stock', 'unit', 'presentation', 'brand'])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $invoiceItems = $invoice->items()->orderBy('item_number')->get()->keyBy('item_number');

        foreach (array_values($requestedItems) as $index => $requestedItem) {
            if (empty($requestedItem['warehouse_dispatch_item_id'])) {
                continue;
            }
            $dispatchItem = $dispatchItems->get((int) ($requestedItem['warehouse_dispatch_item_id'] ?? 0));
            $invoiceItem = $invoiceItems->get($index + 1);
            if (! $dispatchItem || ! $invoiceItem) {
                throw ValidationException::withMessages([
                    'items' => 'No se pudo establecer la trazabilidad entre la factura y el despacho confirmado.',
                ]);
            }

            $quantity = round((float) ($requestedItem['quantity'] ?? 0), 4);
            $invoiceItem->update([
                'article_id' => $dispatchItem->article_id,
                'customer_purchase_order_item_id' => $dispatchItem->customer_purchase_order_item_id,
                'unit_name' => $dispatchItem->unit?->description ?: $invoiceItem->unit_name,
                'brand_name' => $dispatchItem->brand?->description ?: $invoiceItem->brand_name,
                'presentation_name' => $dispatchItem->presentation?->description ?: $invoiceItem->presentation_name,
                'lot_number' => $dispatchItem->lot_number,
                'expiration_date' => $dispatchItem->expiration_date,
                'origin' => $dispatchItem->stock?->origin,
            ]);
            $invoiceItem->dispatchAllocations()->create([
                'warehouse_dispatch_item_id' => $dispatchItem->id,
                'quantity' => $quantity,
            ]);
        }
    }

    public function releaseDispatchAllocations(ElectronicInvoice $invoice): void
    {
        $invoice->dispatchAllocations()->get()->each->delete();
    }

    public function dispatchTraceability(ElectronicInvoice $invoice): array
    {
        $allocations = ElectronicInvoiceItemDispatchAllocation::withTrashed()
            ->whereHas('invoiceItem', fn ($query) => $query->where('electronic_invoice_id', $invoice->id))
            ->with('dispatchItem.dispatch.warehouse')
            ->get();
        $dispatches = $allocations
            ->map(fn ($allocation) => $allocation->dispatchItem?->dispatch)
            ->filter()
            ->unique('id')
            ->values();
        $warehouses = $dispatches
            ->map(fn ($dispatch) => $dispatch->warehouse)
            ->filter()
            ->unique('id')
            ->values();

        $warehouseLabel = match (true) {
            $warehouses->count() === 1 => collect([
                $warehouses->first()->code,
                $warehouses->first()->name,
            ])->filter()->implode(' | '),
            $warehouses->count() > 1 => 'Múltiples almacenes — según despachos',
            default => null,
        };

        return [
            'dispatch_backed' => $allocations->isNotEmpty(),
            'active_allocations' => $allocations->whereNull('deleted_at')->count(),
            'historical_allocations' => $allocations->count(),
            'dispatch_numbers' => $dispatches->pluck('dispatch_number')->filter()->values()->all(),
            'dispatch_label' => $dispatches->pluck('dispatch_number')->filter()->implode(', '),
            'warehouse_id' => $warehouses->count() === 1 ? $warehouses->first()->id : null,
            'warehouse_label' => $warehouseLabel,
        ];
    }

    public function isDispatchBackedInvoice(ElectronicInvoice $invoice): bool
    {
        return $invoice->dispatchAllocations()->exists();
    }

    public function shouldUseDispatchBacking(
        CustomerPurchaseOrder $order,
        ?ElectronicInvoice $currentInvoice = null
    ): bool {
        if ($currentInvoice
            && ! $this->isDispatchBackedInvoice($currentInvoice)
            && $this->hasLegacyStockMovement($currentInvoice)) {
            return false;
        }

        return $this->hasConfirmedDispatches($order);
    }

    public function hasConfirmedDispatches(CustomerPurchaseOrder $order): bool
    {
        return WarehouseDispatch::query()
            ->where('customer_purchase_order_id', $order->id)
            ->where('status', WarehouseDispatch::STATUS_CONFIRMED)
            ->whereHas('items', fn ($items) => $items->whereIn('status', $this->confirmedItemStatuses()))
            ->exists();
    }

    public function summary(CustomerPurchaseOrder $order): array
    {
        $order->loadMissing(['items', 'electronicInvoices.items', 'electronicInvoices.collections']);
        $invoices = $order->electronicInvoices->filter(fn ($invoice) => $this->countsForBilling($invoice));
        $billed = $invoices->sum(fn ($invoice) => (float) $invoice->total_amount);
        $paid = $invoices->sum(fn ($invoice) => (float) $invoice->paid_amount);
        $pending = $invoices->sum(fn ($invoice) => (float) $invoice->pending_amount);
        $billedQuantities = $this->billedQuantities($order);
        $fullyBilled = $order->items->where('status', '!=', 'deleted')->every(
            fn ($item) => (float) ($billedQuantities[$item->id] ?? 0) + self::QUANTITY_TOLERANCE >= (float) $item->quantity
        );

        return [
            'order_total' => (float) $order->grand_total,
            'billed_amount' => round($billed, 10),
            'unbilled_amount' => max(0, round((float) $order->grand_total - $billed, 10)),
            'paid_amount' => round($paid, 10),
            'pending_amount' => round($pending, 10),
            'billing_status' => $invoices->isEmpty() ? 'unbilled' : ($fullyBilled ? 'fully_invoiced' : 'partially_invoiced'),
            'collection_status' => $invoices->isEmpty() ? 'unbilled' : ($pending <= self::QUANTITY_TOLERANCE ? 'paid' : ($paid > 0 ? 'partial' : 'pending')),
            'invoices' => $invoices->values(),
        ];
    }

    private function dispatchBackedItems(CustomerPurchaseOrder $order): array
    {
        $dispatchItems = $this->confirmedDispatchItemsQuery($order)
            ->with([
                'customerPurchaseOrderItem.article.unit.sunatUnit.catalog',
                'customerPurchaseOrderItem.unit',
                'customerPurchaseOrderItem.presentation',
                'customerPurchaseOrderItem.brand',
                'article.unit.sunatUnit.catalog', 'stock', 'unit', 'presentation', 'brand', 'dispatch.warehouse',
            ])
            ->get();
        $allocated = $this->allocatedQuantities($dispatchItems->pluck('id'));
        $returned = $this->returnedQuantities($dispatchItems->pluck('id'));

        return $dispatchItems->map(function (WarehouseDispatchItem $dispatchItem) use ($allocated, $returned, $order) {
            $orderItem = $dispatchItem->customerPurchaseOrderItem;
            $billed = (float) ($allocated[$dispatchItem->id] ?? 0);
            $returnedQuantity = (float) ($returned[$dispatchItem->id] ?? 0);
            $pending = max(0, round((float) $dispatchItem->quantity - $returnedQuantity - $billed, 4));

            return [
                'warehouse_dispatch_item_id' => $dispatchItem->id,
                'warehouse_dispatch_number' => $dispatchItem->dispatch?->dispatch_number,
                'warehouse_id' => $dispatchItem->dispatch?->warehouse_id,
                'warehouse_code' => $dispatchItem->dispatch?->warehouse?->code,
                'warehouse_name' => $dispatchItem->dispatch?->warehouse?->name,
                'customer_purchase_order_item_id' => $orderItem?->id,
                'article_id' => $dispatchItem->article_id,
                'product_code' => $orderItem?->article_code ?: $orderItem?->article?->code,
                'description' => $orderItem?->billing_name_snapshot ?: $orderItem?->article?->billing_name,
                'brand_name' => $dispatchItem->brand?->description ?: $orderItem?->brand?->description,
                'presentation_name' => $dispatchItem->presentation?->description ?: $orderItem?->presentation?->description,
                'unit_code' => $this->sunatUnitPolicy->codeForArticle($dispatchItem->article, 'items'),
                'origin' => $dispatchItem->stock?->origin,
                'lot_number' => $dispatchItem->lot_number,
                'expiration_date' => $dispatchItem->expiration_date?->format('Y-m-d'),
                'ordered_quantity' => (float) ($orderItem?->quantity ?? 0),
                'dispatched_quantity' => (float) $dispatchItem->quantity,
                'returned_quantity' => $returnedQuantity,
                'billed_quantity' => $billed,
                'pending_quantity' => $pending,
                'quantity' => $pending,
                'unit_price' => $orderItem?->unit_price,
                'tax_affectation_code' => $this->orderItemTaxAffectation($orderItem, $order),
            ];
        })->filter(fn ($item) => $item['pending_quantity'] > self::QUANTITY_TOLERANCE)->values()->all();
    }

    private function dispatchWarehouseContext(array $items): array
    {
        $warehouses = collect($items)
            ->filter(fn (array $item) => ! empty($item['warehouse_id']))
            ->unique('warehouse_id')
            ->values();

        if ($warehouses->count() === 1) {
            $warehouse = $warehouses->first();
            $code = trim((string) ($warehouse['warehouse_code'] ?? ''));
            $name = trim((string) ($warehouse['warehouse_name'] ?? ''));

            return [
                'mode' => 'single',
                'warehouse_id' => (int) $warehouse['warehouse_id'],
                'warehouse_code' => $code ?: null,
                'warehouse_name' => $name ?: null,
                'label' => collect([$code, $name])->filter()->implode(' | '),
            ];
        }

        if ($warehouses->count() > 1) {
            return [
                'mode' => 'multiple',
                'warehouse_id' => null,
                'warehouse_code' => null,
                'warehouse_name' => null,
                'label' => 'Múltiples almacenes — según despachos',
            ];
        }

        return [
            'mode' => 'unavailable',
            'warehouse_id' => null,
            'warehouse_code' => null,
            'warehouse_name' => null,
            'label' => 'Almacén no identificado — revisar despachos',
        ];
    }

    private function commercialPendingItems(CustomerPurchaseOrder $order): array
    {
        $commercialArticleIds = $order->items
            ->filter(fn ($item) => $item->article
                && ! $this->articleInventoryPolicy->canParticipateInInventory($item->article))
            ->pluck('article_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return collect($this->legacyPendingItems($order))
            ->filter(fn (array $item) => in_array((int) $item['article_id'], $commercialArticleIds, true))
            ->values()
            ->all();
    }

    private function legacyPendingItems(CustomerPurchaseOrder $order): array
    {
        $billed = $this->billedQuantities($order);

        return $order->items->map(function ($item) use ($billed, $order) {
            $pending = max(0, (float) $item->quantity - (float) ($billed[$item->id] ?? 0));

            return [
                'warehouse_dispatch_item_id' => null,
                'customer_purchase_order_item_id' => $item->id,
                'article_id' => $item->article_id,
                'product_code' => $item->article_code ?: $item->article?->code,
                'description' => $item->billing_name_snapshot ?: $item->article?->billing_name,
                'brand_name' => $item->brand?->description,
                'presentation_name' => $item->presentation?->description,
                'unit_code' => $this->sunatUnitPolicy->codeForArticle($item->article, 'items'),
                'origin' => $item->origin,
                'expiration_date' => optional($item->expiration_date)->format('Y-m-d'),
                'ordered_quantity' => (float) $item->quantity,
                'billed_quantity' => (float) ($billed[$item->id] ?? 0),
                'pending_quantity' => $pending,
                'quantity' => $pending,
                'unit_price' => $item->unit_price,
                'tax_affectation_code' => $this->orderItemTaxAffectation($item, $order),
            ];
        })->filter(fn ($item) => $item['pending_quantity'] > self::QUANTITY_TOLERANCE)->values()->all();
    }

    private function orderItemTaxAffectation($item, CustomerPurchaseOrder $order): string
    {
        $code = trim((string) ($item?->tax_affectation_code ?? ''));
        if (in_array($code, ArticleSalesTaxPolicy::ALLOWED, true)) {
            return $code;
        }

        // Compatibilidad histórica: antes de Fase 2.6 la orden solo distinguía
        // afecto/no afecto. Para snapshots antiguos conservamos exactamente esa
        // semántica: true → 10; false → 20. Nunca inferimos 30 retroactivamente.
        return $order->affect_igv
            ? ArticleSalesTaxPolicy::TAXABLE
            : ArticleSalesTaxPolicy::EXONERATED;
    }

    private function validateDispatchBackedItems(
        CustomerPurchaseOrder $order,
        array $items,
        ?ElectronicInvoice $currentInvoice = null
    ): void {
        $dispatchItems = $this->confirmedDispatchItemsQuery($order)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($dispatchItems->isEmpty() && collect($items)->contains(fn ($item) => ! empty($item['warehouse_dispatch_item_id']))) {
            throw ValidationException::withMessages([
                'items' => 'La orden no tiene mercadería físicamente despachada disponible para facturar.',
            ]);
        }
        $allocated = $this->allocatedQuantities($dispatchItems->keys(), $currentInvoice?->id);
        $returned = $this->returnedQuantities($dispatchItems->keys());
        $seen = [];

        foreach ($items as $item) {
            $dispatchItemId = (int) ($item['warehouse_dispatch_item_id'] ?? 0);
            if ($dispatchItemId === 0) {
                $this->validateCommercialItem($order, $item, $currentInvoice);
                continue;
            }
            $dispatchItem = $dispatchItems->get($dispatchItemId);
            if (! $dispatchItem) {
                throw ValidationException::withMessages([
                    'items' => 'Todos los artículos deben corresponder a detalles de despachos confirmados de la orden.',
                ]);
            }
            if (isset($seen[$dispatchItemId])) {
                throw ValidationException::withMessages([
                    'items' => 'Un detalle de despacho no puede repetirse dentro de la misma factura.',
                ]);
            }
            $seen[$dispatchItemId] = true;

            if ((int) ($item['customer_purchase_order_item_id'] ?? 0) !== (int) $dispatchItem->customer_purchase_order_item_id
                || (! empty($item['article_id']) && (int) $item['article_id'] !== (int) $dispatchItem->article_id)) {
                throw ValidationException::withMessages([
                    'items' => 'El artículo facturado no coincide con el detalle físicamente despachado.',
                ]);
            }
            $quantity = round((float) ($item['quantity'] ?? 0), 4);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['items' => 'La cantidad a facturar debe ser mayor a cero.']);
            }
            $available = max(0, round(
                (float) $dispatchItem->quantity
                    - (float) ($returned[$dispatchItemId] ?? 0)
                    - (float) ($allocated[$dispatchItemId] ?? 0),
                4
            ));
            if ($quantity > $available + self::QUANTITY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'items' => "La cantidad a facturar supera el saldo físicamente despachado ({$available}).",
                ]);
            }
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'La factura debe incluir artículos pendientes de facturar.']);
        }
    }

    private function validateCommercialItem(
        CustomerPurchaseOrder $order,
        array $item,
        ?ElectronicInvoice $currentInvoice = null
    ): void {
        $orderItem = $order->items()
            ->where('status', '!=', 'deleted')
            ->with('article')
            ->find((int) ($item['customer_purchase_order_item_id'] ?? 0));

        if (! $orderItem || ! $orderItem->article
            || $this->articleInventoryPolicy->canParticipateInInventory($orderItem->article)) {
            throw ValidationException::withMessages([
                'items' => 'Un producto inventariable requiere un detalle de despacho confirmado para facturarse.',
            ]);
        }
        if (! empty($item['article_id']) && (int) $item['article_id'] !== (int) $orderItem->article_id) {
            throw ValidationException::withMessages(['items' => 'El artículo facturado no pertenece a la orden seleccionada.']);
        }

        $billed = $this->billedQuantities($order, $currentInvoice?->id);
        $quantity = round((float) ($item['quantity'] ?? 0), 4);
        $pending = max(0, (float) $orderItem->quantity - (float) ($billed[$orderItem->id] ?? 0));
        if ($quantity <= 0 || $quantity > $pending + self::QUANTITY_TOLERANCE) {
            throw ValidationException::withMessages([
                'items' => "La cantidad a facturar de {$orderItem->billing_name_snapshot} supera el saldo pendiente ({$pending}).",
            ]);
        }
    }

    private function validateLegacyItems(
        CustomerPurchaseOrder $order,
        array $items,
        ?ElectronicInvoice $currentInvoice = null
    ): void {
        $orderItems = $order->items()->where('status', '!=', 'deleted')->get()->keyBy('id');
        $billed = $this->billedQuantities($order, $currentInvoice?->id);
        $requested = collect($items)->groupBy('customer_purchase_order_item_id')
            ->map(fn (Collection $rows) => $rows->sum(fn ($row) => (float) ($row['quantity'] ?? 0)));

        foreach ($requested as $itemId => $quantity) {
            $orderItem = $orderItems->get((int) $itemId);
            if (! $orderItem) {
                throw ValidationException::withMessages(['items' => 'Todos los artículos deben pertenecer a la orden de compra seleccionada.']);
            }
            $pending = max(0, (float) $orderItem->quantity - (float) ($billed[$orderItem->id] ?? 0));
            if ($quantity > $pending + self::QUANTITY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'items' => "La cantidad a facturar de {$orderItem->billing_name_snapshot} supera el saldo pendiente ({$pending}).",
                ]);
            }
        }

        if ($requested->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'La factura debe incluir artículos pendientes de la orden.']);
        }
    }

    private function confirmedDispatchItemsQuery(CustomerPurchaseOrder $order): Builder
    {
        return WarehouseDispatchItem::query()
            ->select('warehouse_dispatch_items.*')
            ->join('warehouse_dispatches', 'warehouse_dispatches.id', '=', 'warehouse_dispatch_items.warehouse_dispatch_id')
            ->where('warehouse_dispatches.customer_purchase_order_id', $order->id)
            ->where('warehouse_dispatches.status', WarehouseDispatch::STATUS_CONFIRMED)
            ->whereIn('warehouse_dispatch_items.status', $this->confirmedItemStatuses())
            ->orderBy('warehouse_dispatches.dispatch_date')
            ->orderBy('warehouse_dispatches.id')
            ->orderBy('warehouse_dispatch_items.id');
    }

    private function allocatedQuantities(Collection $dispatchItemIds, ?int $excludeInvoiceId = null): Collection
    {
        if ($dispatchItemIds->isEmpty()) {
            return collect();
        }

        return ElectronicInvoiceItemDispatchAllocation::query()
            ->join('electronic_invoice_items as invoice_items', 'invoice_items.id', '=', 'electronic_invoice_item_dispatch_allocations.electronic_invoice_item_id')
            ->join('electronic_invoices as invoices', 'invoices.id', '=', 'invoice_items.electronic_invoice_id')
            ->whereIn('electronic_invoice_item_dispatch_allocations.warehouse_dispatch_item_id', $dispatchItemIds)
            ->whereNull('invoices.deleted_at')
            ->where('invoices.status', 'generated')
            ->where('invoices.is_voided', false)
            ->when($excludeInvoiceId, fn ($query) => $query->where('invoices.id', '!=', $excludeInvoiceId))
            ->groupBy('electronic_invoice_item_dispatch_allocations.warehouse_dispatch_item_id')
            ->selectRaw('electronic_invoice_item_dispatch_allocations.warehouse_dispatch_item_id, SUM(electronic_invoice_item_dispatch_allocations.quantity) as total')
            ->pluck('total', 'warehouse_dispatch_item_id');
    }

    private function returnedQuantities(Collection $dispatchItemIds): Collection
    {
        if ($dispatchItemIds->isEmpty() || ! Schema::hasTable('customer_return_items')) {
            return collect();
        }

        return CustomerReturnItem::query()
            ->join('customer_returns', 'customer_returns.id', '=', 'customer_return_items.customer_return_id')
            ->whereIn('customer_return_items.warehouse_dispatch_item_id', $dispatchItemIds)
            ->where('customer_returns.status', CustomerReturn::STATUS_CONFIRMED)
            ->whereNull('customer_returns.deleted_at')
            ->where('customer_return_items.status', CustomerReturnItem::STATUS_CONFIRMED)
            ->groupBy('customer_return_items.warehouse_dispatch_item_id')
            ->selectRaw('customer_return_items.warehouse_dispatch_item_id, SUM(customer_return_items.quantity) total')
            ->pluck('total', 'warehouse_dispatch_item_id');
    }

    private function hasLegacyStockMovement(ElectronicInvoice $invoice): bool
    {
        return $invoice->stock_moved_at !== null
            || WarehouseKardexMovement::query()
                ->where('source_type', ElectronicInvoice::class)
                ->where('source_id', $invoice->id)
                ->where('operation_type', 'electronic_invoice')
                ->exists();
    }

    private function confirmedItemStatuses(): array
    {
        return [
            WarehouseDispatchItem::STATUS_CONFIRMED,
            WarehouseDispatchItem::STATUS_LEGACY_CONFIRMED,
        ];
    }

    private function billedQuantities(CustomerPurchaseOrder $order, ?int $excludeInvoiceId = null): Collection
    {
        $invoices = $order->relationLoaded('electronicInvoices')
            ? $order->electronicInvoices
                ->filter(fn ($invoice) => $this->countsForBilling($invoice))
                ->when($excludeInvoiceId, fn (Collection $rows) => $rows->where('id', '!=', $excludeInvoiceId))
            : ElectronicInvoice::query()
                ->where('customer_purchase_order_id', $order->id)
                ->whereNotIn('status', ['draft', 'cancelled', 'voided'])
                ->where('is_voided', false)
                ->when($excludeInvoiceId, fn ($query) => $query->whereKeyNot($excludeInvoiceId))
                ->with('items')
                ->get();

        return $invoices->flatMap->items
            ->whereNotNull('customer_purchase_order_item_id')
            ->groupBy('customer_purchase_order_item_id')
            ->map(fn (Collection $items) => $items->sum(fn ($item) => (float) $item->quantity));
    }

    private function countsForBilling(ElectronicInvoice $invoice): bool
    {
        return ! in_array($invoice->status, ['draft', 'cancelled', 'voided'], true) && ! $invoice->is_voided;
    }
}

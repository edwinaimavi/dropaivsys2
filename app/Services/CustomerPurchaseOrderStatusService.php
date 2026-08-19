<?php

namespace App\Services;

use App\Models\CustomerPurchaseOrder;
use App\Models\WarehouseEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerPurchaseOrderStatusService
{
    private const ACTIVE_ENTRY_STATUS = 'registered';

    private const QUANTITY_SCALE = 4;

    /**
     * Recalculate one customer order from its actual purchase and warehouse progress.
     *
     * @return array{changed: bool, skipped: bool, previous_status: string, status: string}
     */
    public function syncStatus(CustomerPurchaseOrder|int $order): array
    {
        $order = $order instanceof CustomerPurchaseOrder
            ? $order->refresh()
            : CustomerPurchaseOrder::query()->findOrFail($order);

        $previousStatus = (string) $order->status;

        if ($this->isProtectedFinalStatus($previousStatus)) {
            return $this->result(false, true, $previousStatus, $previousStatus);
        }

        $requestedItems = $order->items()
            ->where('status', '!=', 'deleted')
            ->get(['id', 'article_id', 'quote_item_id', 'market_study_item_id', 'quantity'])
            ->keyBy('id');

        if ($requestedItems->isEmpty()) {
            return $this->apply($order, CustomerPurchaseOrder::STATUS_REGISTERED);
        }

        $requestedByItem = $requestedItems
            ->map(fn ($item) => round((float) $item->quantity, self::QUANTITY_SCALE))
            ->all();
        $customerItemIds = $requestedItems->keys()->map(fn ($id) => (int) $id)->all();

        $historicalSupplierOrderIds = $this->supplierOrderIdsForCustomerOrder(
            (int) $order->id,
            $customerItemIds,
            false
        );
        $activeSupplierOrderIds = $this->supplierOrderIdsForCustomerOrder(
            (int) $order->id,
            $customerItemIds,
            true
        );

        $supplierItems = DB::table('supplier_purchase_order_items')
            ->where(function ($query) use ($historicalSupplierOrderIds, $customerItemIds) {
                if ($historicalSupplierOrderIds->isNotEmpty()) {
                    $query->whereIn('supplier_purchase_order_id', $historicalSupplierOrderIds->all());
                }

                if (! empty($customerItemIds)) {
                    $method = $historicalSupplierOrderIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('customer_purchase_order_item_id', $customerItemIds);
                }
            })
            ->get([
                'id',
                'supplier_purchase_order_id',
                'customer_purchase_order_item_id',
                'article_id',
                'quote_item_id',
                'market_study_item_id',
                'quantity',
                'status',
            ]);

        $fallbackMap = $this->unambiguousCustomerItemMapBySupplierOrder(
            $historicalSupplierOrderIds,
            $requestedItems
        );
        $supplierItemToCustomerItem = $supplierItems
            ->mapWithKeys(function ($supplierItem) use ($requestedItems, $fallbackMap) {
                $customerItemId = (int) ($supplierItem->customer_purchase_order_item_id ?? 0);

                if (! $requestedItems->has($customerItemId)) {
                    $customerItemId = $this->fallbackCustomerItemId($supplierItem, $fallbackMap);
                }

                return $customerItemId > 0
                    ? [(int) $supplierItem->id => $customerItemId]
                    : [];
            });

        $purchasedByItem = [];
        foreach ($supplierItems as $supplierItem) {
            if (! $activeSupplierOrderIds->contains((int) $supplierItem->supplier_purchase_order_id)
                || strtolower((string) $supplierItem->status) === 'deleted') {
                continue;
            }

            $customerItemId = $supplierItemToCustomerItem->get((int) $supplierItem->id);
            if (! $customerItemId) {
                continue;
            }

            $purchasedByItem[$customerItemId] = round(
                (float) ($purchasedByItem[$customerItemId] ?? 0) + (float) $supplierItem->quantity,
                self::QUANTITY_SCALE
            );
        }

        $enteredByItem = $this->enteredQuantities(
            $historicalSupplierOrderIds,
            $supplierItemToCustomerItem,
            $fallbackMap
        );

        $status = CustomerPurchaseOrder::supplyStatusFromQuantities(
            $requestedByItem,
            $purchasedByItem,
            $enteredByItem,
            filled($order->attention_document_path),
            $activeSupplierOrderIds->isNotEmpty()
        );

        return $this->apply($order, $status);
    }

    /**
     * Backward-compatible alias for integrations created before syncStatus became
     * the canonical entry point.
     */
    public function recalculate(CustomerPurchaseOrder|int $order): array
    {
        return $this->syncStatus($order);
    }

    /** @return Collection<int, array{changed: bool, skipped: bool, previous_status: string, status: string, order: CustomerPurchaseOrder}> */
    public function syncMany(iterable $customerPurchaseOrderIds): Collection
    {
        $ids = collect($customerPurchaseOrderIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return CustomerPurchaseOrder::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->map(function (CustomerPurchaseOrder $order) {
                return $this->syncStatus($order) + ['order' => $order];
            });
    }

    public function recalculateMany(iterable $customerPurchaseOrderIds): Collection
    {
        return $this->syncMany($customerPurchaseOrderIds);
    }

    public function customerOrderIdsForSupplierOrder(int $supplierPurchaseOrderId): Collection
    {
        $pivotIds = DB::table('supplier_purchase_order_customer_purchase_order')
            ->where('supplier_purchase_order_id', $supplierPurchaseOrderId)
            ->pluck('customer_purchase_order_id');
        $legacyId = DB::table('supplier_purchase_orders')
            ->where('id', $supplierPurchaseOrderId)
            ->value('customer_purchase_order_id');
        $itemIds = DB::table('supplier_purchase_order_items as supplier_items')
            ->join(
                'customer_purchase_order_items as customer_items',
                'customer_items.id',
                '=',
                'supplier_items.customer_purchase_order_item_id'
            )
            ->where('supplier_items.supplier_purchase_order_id', $supplierPurchaseOrderId)
            ->pluck('customer_items.customer_purchase_order_id');

        return $pivotIds
            ->merge($itemIds)
            ->push($legacyId)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function customerOrderIdsForWarehouseEntry(WarehouseEntry $entry): Collection
    {
        $ids = $entry->supplier_purchase_order_id
            ? $this->customerOrderIdsForSupplierOrder((int) $entry->supplier_purchase_order_id)
            : collect();

        $itemIds = DB::table('warehouse_entry_items as entry_items')
            ->join(
                'supplier_purchase_order_items as supplier_items',
                'supplier_items.id',
                '=',
                'entry_items.supplier_purchase_order_item_id'
            )
            ->join(
                'customer_purchase_order_items as customer_items',
                'customer_items.id',
                '=',
                'supplier_items.customer_purchase_order_item_id'
            )
            ->where('entry_items.warehouse_entry_id', $entry->id)
            ->pluck('customer_items.customer_purchase_order_id');

        return $ids
            ->merge($itemIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function enteredQuantities(
        Collection $historicalSupplierOrderIds,
        Collection $supplierItemToCustomerItem,
        Collection $fallbackMap
    ): array {
        if ($historicalSupplierOrderIds->isEmpty() && $supplierItemToCustomerItem->isEmpty()) {
            return [];
        }

        $entryItems = DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->whereNull('entries.deleted_at')
            ->where('entries.status', self::ACTIVE_ENTRY_STATUS)
            ->where('items.status', '!=', 'deleted')
            ->where(function ($query) use ($historicalSupplierOrderIds, $supplierItemToCustomerItem) {
                if ($historicalSupplierOrderIds->isNotEmpty()) {
                    $query->whereIn('entries.supplier_purchase_order_id', $historicalSupplierOrderIds->all());
                }

                if ($supplierItemToCustomerItem->isNotEmpty()) {
                    $method = $historicalSupplierOrderIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('items.supplier_purchase_order_item_id', $supplierItemToCustomerItem->keys()->all());
                }
            })
            ->get([
                'items.supplier_purchase_order_item_id',
                'items.article_id',
                'items.quantity',
                'entries.supplier_purchase_order_id',
            ]);

        $entered = [];
        foreach ($entryItems as $entryItem) {
            $customerItemId = $supplierItemToCustomerItem->get(
                (int) ($entryItem->supplier_purchase_order_item_id ?? 0)
            );

            if (! $customerItemId) {
                $customerItemId = $fallbackMap->get($this->fallbackKey(
                    (int) $entryItem->supplier_purchase_order_id,
                    'article',
                    (int) $entryItem->article_id
                ));
            }

            if (! $customerItemId) {
                continue;
            }

            $entered[$customerItemId] = round(
                (float) ($entered[$customerItemId] ?? 0) + (float) $entryItem->quantity,
                self::QUANTITY_SCALE
            );
        }

        return $entered;
    }

    private function supplierOrderIdsForCustomerOrder(
        int $customerPurchaseOrderId,
        array $customerItemIds,
        bool $onlyActive
    ): Collection {
        $ids = DB::table('supplier_purchase_orders')
            ->where('customer_purchase_order_id', $customerPurchaseOrderId)
            ->when($onlyActive, function ($query) {
                $query->whereNull('deleted_at')->where('status', '!=', 'cancelled');
            })
            ->pluck('id');

        $pivotIds = DB::table('supplier_purchase_order_customer_purchase_order as links')
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'links.supplier_purchase_order_id')
            ->where('links.customer_purchase_order_id', $customerPurchaseOrderId)
            ->when($onlyActive, function ($query) {
                $query->whereNull('orders.deleted_at')->where('orders.status', '!=', 'cancelled');
            })
            ->pluck('links.supplier_purchase_order_id');

        $itemIds = DB::table('supplier_purchase_order_items as items')
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'items.supplier_purchase_order_id')
            ->whereIn('items.customer_purchase_order_item_id', $customerItemIds)
            ->when($onlyActive, function ($query) {
                $query->whereNull('orders.deleted_at')->where('orders.status', '!=', 'cancelled');
            })
            ->pluck('items.supplier_purchase_order_id');

        return $ids->merge($pivotIds)->merge($itemIds)->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function unambiguousCustomerItemMapBySupplierOrder(
        Collection $supplierOrderIds,
        Collection $requestedItems
    ): Collection {
        if ($supplierOrderIds->isEmpty()) {
            return collect();
        }

        $relatedCustomerOrderIds = collect();
        foreach ($supplierOrderIds as $supplierOrderId) {
            $relatedCustomerOrderIds = $relatedCustomerOrderIds->merge(
                $this->customerOrderIdsForSupplierOrder((int) $supplierOrderId)
            );
        }

        $candidateItems = DB::table('customer_purchase_order_items')
            ->whereIn('customer_purchase_order_id', $relatedCustomerOrderIds->unique()->all())
            ->where('status', '!=', 'deleted')
            ->get([
                'id',
                'customer_purchase_order_id',
                'article_id',
                'quote_item_id',
                'market_study_item_id',
            ]);

        $map = collect();
        foreach ($supplierOrderIds as $supplierOrderId) {
            $orderIds = $this->customerOrderIdsForSupplierOrder((int) $supplierOrderId);
            $items = $candidateItems->whereIn('customer_purchase_order_id', $orderIds->all());

            foreach ([
                'quote_item_id' => 'quote',
                'market_study_item_id' => 'market',
                'article_id' => 'article',
            ] as $column => $type) {
                foreach ($items->filter(fn ($item) => filled($item->{$column}))->groupBy($column) as $value => $matches) {
                    if ($matches->count() !== 1) {
                        continue;
                    }

                    $customerItemId = (int) $matches->first()->id;
                    if ($requestedItems->has($customerItemId)) {
                        $map->put(
                            $this->fallbackKey((int) $supplierOrderId, $type, (int) $value),
                            $customerItemId
                        );
                    }
                }
            }
        }

        return $map;
    }

    private function fallbackCustomerItemId(object $supplierItem, Collection $fallbackMap): int
    {
        foreach ([
            'quote' => $supplierItem->quote_item_id ?? null,
            'market' => $supplierItem->market_study_item_id ?? null,
            'article' => $supplierItem->article_id ?? null,
        ] as $type => $value) {
            if (! $value) {
                continue;
            }

            $customerItemId = (int) $fallbackMap->get($this->fallbackKey(
                (int) $supplierItem->supplier_purchase_order_id,
                $type,
                (int) $value
            ), 0);

            if ($customerItemId > 0) {
                return $customerItemId;
            }
        }

        return 0;
    }

    private function fallbackKey(int $supplierOrderId, string $type, int $value): string
    {
        return $supplierOrderId.':'.$type.':'.$value;
    }

    private function isProtectedFinalStatus(string $status): bool
    {
        return in_array($status, [
            'cancelled',
            'completed',
            'delivered',
            'invoiced',
            CustomerPurchaseOrder::STATUS_ATTENDED,
            CustomerPurchaseOrder::STATUS_NOT_ATTENDED,
        ], true);
    }

    private function apply(CustomerPurchaseOrder $order, string $status): array
    {
        $previousStatus = (string) $order->status;
        if ($previousStatus === $status) {
            return $this->result(false, false, $previousStatus, $status);
        }

        $order->forceFill(['status' => $status])->save();

        return $this->result(true, false, $previousStatus, $status);
    }

    private function result(bool $changed, bool $skipped, string $previousStatus, string $status): array
    {
        return compact('changed', 'skipped', 'status') + [
            'previous_status' => $previousStatus,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\CustomerPurchaseOrderItem;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseEntryItemAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseEntryAllocationService
{
    private const EPSILON = 0.0001;

    public function sync(WarehouseEntryItem $entryItem, array $rows, ?int $userId): void
    {
        $entryItem->loadMissing('warehouseEntry');
        $retained = [];
        $allocated = 0.0;

        foreach (array_values($rows) as $index => $row) {
            $quantity = round((float) ($row['quantity_allocated'] ?? 0), 4);
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.allocations" => 'Cada asignación debe tener una cantidad mayor a cero.',
                ]);
            }

            $allocated = round($allocated + $quantity, 4);
            if ($allocated > (float) $entryItem->quantity + self::EPSILON) {
                throw ValidationException::withMessages([
                    'items' => 'La cantidad asignada no puede superar la cantidad ingresada del artículo.',
                ]);
            }

            $type = (string) ($row['allocation_type'] ?? '');
            $payload = match ($type) {
                WarehouseEntryItemAllocation::TYPE_CUSTOMER_ORDER => $this->customerPayload($entryItem, $row, $quantity),
                WarehouseEntryItemAllocation::TYPE_SUPPLIER_ORDER => $this->supplierPayload($entryItem, $row, $quantity),
                WarehouseEntryItemAllocation::TYPE_FREE_STOCK => $this->freeStockPayload($entryItem),
                default => throw ValidationException::withMessages([
                    'items' => 'El destino seleccionado para el artículo no es válido.',
                ]),
            };

            $allocation = ! empty($row['id'])
                ? $entryItem->allocations()->lockForUpdate()->findOrFail($row['id'])
                : $entryItem->allocations()->make(['created_by' => $userId]);
            $unitCost = (float) ($entryItem->unit_price ?: 0);
            $allocation->fill($payload + [
                'warehouse_entry_id' => $entryItem->warehouse_entry_id,
                'article_id' => $entryItem->article_id,
                'quantity_allocated' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'allocation_type' => $type,
                'status' => 'active',
                'updated_by' => $userId,
                'deleted_by' => null,
            ])->save();
            $retained[] = $allocation->id;
        }

        $entryItem->allocations()
            ->whereNotIn('id', $retained ?: [0])
            ->get()
            ->each(function (WarehouseEntryItemAllocation $allocation) use ($userId) {
                $allocation->forceFill(['status' => 'deleted', 'deleted_by' => $userId, 'updated_by' => $userId])->save();
                $allocation->delete();
            });
    }

    private function customerPayload(WarehouseEntryItem $entryItem, array $row, float $quantity): array
    {
        $customerItem = CustomerPurchaseOrderItem::query()
            ->with('purchaseOrder:id,company_id,currency_id,status')
            ->lockForUpdate()
            ->findOrFail($row['customer_purchase_order_item_id'] ?? 0);

        if ((int) $customerItem->article_id !== (int) $entryItem->article_id
            || (int) $customerItem->purchaseOrder->company_id !== (int) $entryItem->warehouseEntry->company_id
            || in_array(strtolower((string) $customerItem->purchaseOrder->status), ['cancelled', 'completed', 'delivered', 'invoiced'], true)) {
            throw ValidationException::withMessages([
                'items' => 'La OC cliente seleccionada no es compatible con el artículo, empresa o estado del ingreso.',
            ]);
        }

        $alreadyAllocated = DB::table('warehouse_entry_item_allocations as allocations')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'allocations.warehouse_entry_id')
            ->where('allocations.customer_purchase_order_item_id', $customerItem->id)
            ->where('allocations.status', 'active')
            ->whereNull('allocations.deleted_at')
            ->whereNull('entries.deleted_at')
            ->where('entries.status', 'registered')
            ->when(! empty($row['id']), fn ($query) => $query->where('allocations.id', '!=', $row['id']))
            ->sum('allocations.quantity_allocated');
        $pending = max(round((float) $customerItem->quantity - (float) $alreadyAllocated, 4), 0);

        if ($quantity > $pending + self::EPSILON) {
            throw ValidationException::withMessages([
                'items' => "La cantidad asignada supera el saldo pendiente de la OC cliente ({$pending}).",
            ]);
        }

        return [
            'customer_purchase_order_id' => $customerItem->customer_purchase_order_id,
            'customer_purchase_order_item_id' => $customerItem->id,
            'supplier_purchase_order_id' => $row['supplier_purchase_order_id'] ?? $entryItem->warehouseEntry->supplier_purchase_order_id,
            'supplier_purchase_order_item_id' => $row['supplier_purchase_order_item_id'] ?? $entryItem->supplier_purchase_order_item_id,
        ];
    }

    private function supplierPayload(WarehouseEntryItem $entryItem, array $row, float $quantity): array
    {
        $supplierItem = SupplierPurchaseOrderItem::query()
            ->with('purchaseOrder:id,company_id,supplier_id,currency_id,status')
            ->lockForUpdate()
            ->findOrFail($row['supplier_purchase_order_item_id'] ?? $entryItem->supplier_purchase_order_item_id ?? 0);

        if ((int) $supplierItem->article_id !== (int) $entryItem->article_id
            || (int) $supplierItem->purchaseOrder->company_id !== (int) $entryItem->warehouseEntry->company_id
            || (int) $supplierItem->purchaseOrder->supplier_id !== (int) $entryItem->warehouseEntry->supplier_id
            || (int) $supplierItem->purchaseOrder->currency_id !== (int) $entryItem->warehouseEntry->currency_id
            || strtolower((string) $supplierItem->purchaseOrder->status) === 'cancelled') {
            throw ValidationException::withMessages([
                'items' => 'La OC proveedor seleccionada no es compatible con este ingreso.',
            ]);
        }

        $alreadyAllocated = DB::table('warehouse_entry_item_allocations as allocations')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'allocations.warehouse_entry_id')
            ->where('allocations.supplier_purchase_order_item_id', $supplierItem->id)
            ->where('allocations.status', 'active')
            ->whereNull('allocations.deleted_at')
            ->whereNull('entries.deleted_at')
            ->where('entries.status', 'registered')
            ->when(! empty($row['id']), fn ($query) => $query->where('allocations.id', '!=', $row['id']))
            ->sum('allocations.quantity_allocated');
        $pending = max(round((float) $supplierItem->quantity - (float) $alreadyAllocated, 4), 0);
        if ($quantity > $pending + self::EPSILON) {
            throw ValidationException::withMessages([
                'items' => "La cantidad asignada supera el saldo pendiente de la OC proveedor ({$pending}).",
            ]);
        }

        return [
            'customer_purchase_order_id' => null,
            'customer_purchase_order_item_id' => null,
            'supplier_purchase_order_id' => $supplierItem->supplier_purchase_order_id,
            'supplier_purchase_order_item_id' => $supplierItem->id,
        ];
    }

    private function freeStockPayload(WarehouseEntryItem $entryItem): array
    {
        return [
            'customer_purchase_order_id' => null,
            'customer_purchase_order_item_id' => null,
            'supplier_purchase_order_id' => $entryItem->warehouseEntry->supplier_purchase_order_id,
            'supplier_purchase_order_item_id' => $entryItem->supplier_purchase_order_item_id,
        ];
    }
}

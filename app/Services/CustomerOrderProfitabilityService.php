<?php

namespace App\Services;

use App\Models\CustomerOrderProfitabilityAnalysis;
use App\Models\CustomerPurchaseOrder;
use App\Models\WarehouseEntryExpense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerOrderProfitabilityService
{
    public const MODE_WITHOUT_IGV = 'without_igv';
    public const MODE_WITH_IGV = 'with_igv';
    public const IGV_RATE = 18.0;
    public const INCOME_TAX_RATE = 29.5;

    public function calculate(CustomerPurchaseOrder $order, string $mode = self::MODE_WITHOUT_IGV): array
    {
        $mode = in_array($mode, [self::MODE_WITHOUT_IGV, self::MODE_WITH_IGV], true) ? $mode : self::MODE_WITHOUT_IGV;
        $order->loadMissing(['customer', 'company', 'currency', 'documents.documentType', 'items.article', 'items.unit', 'items.presentation', 'items.brand']);
        $itemIds = $order->items->where('status', '!=', 'deleted')->pluck('id');
        $directSupplierOrderIds = DB::table('supplier_purchase_orders')
            ->where('customer_purchase_order_id', $order->id)
            ->whereNull('deleted_at')->where('status', '!=', 'cancelled')->pluck('id');
        $supplierItems = DB::table('supplier_purchase_order_items as items')
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'items.supplier_purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'orders.supplier_id')
            ->where(function ($query) use ($itemIds, $directSupplierOrderIds) {
                $query->whereIn('items.customer_purchase_order_item_id', $itemIds)
                    ->orWhereIn('items.supplier_purchase_order_id', $directSupplierOrderIds);
            })
            ->whereNull('orders.deleted_at')->where('orders.status', '!=', 'cancelled')->where('items.status', '!=', 'deleted')
            ->select('items.*', 'orders.code as order_code', 'orders.affect_igv as order_affect_igv', 'orders.currency_id as order_currency_id', 'orders.status as order_status', 'orders.created_at as order_date', 'suppliers.business_name as supplier_name')
            ->get();
        $supplierOrderIds = $supplierItems->pluck('supplier_purchase_order_id')->unique();
        $entryIds = DB::table('warehouse_entries as entries')
            ->leftJoin('warehouse_entry_items as items', 'items.warehouse_entry_id', '=', 'entries.id')
            ->where(function ($query) use ($supplierItems, $supplierOrderIds) {
                $query->whereIn('items.supplier_purchase_order_item_id', $supplierItems->pluck('id'))
                    ->orWhereIn('entries.supplier_purchase_order_id', $supplierOrderIds);
            })->whereNull('entries.deleted_at')->where('entries.status', 'registered')
            ->pluck('entries.id')->unique();
        $costs = WarehouseEntryExpense::query()->with(['documents', 'warehouseEntry:id,entry_number,document_date'])
            ->whereIn('warehouse_entry_id', $entryIds)->where('status', 'ACTIVE')->get();

        $saleTotal = round((float) $order->items->where('status', '!=', 'deleted')->sum(fn ($item) => (float) ($item->line_total ?: ((float) $item->quantity * (float) $item->unit_price))), 2);
        $purchaseTotal = round((float) $supplierItems->sum(fn ($item) => (float) ($item->line_total ?: ((float) $item->quantity * (float) $item->unit_price))), 2);
        $withoutReceipt = $costs->reject(fn ($cost) => $this->isValidPaymentDocument($cost));
        $operationalTransportCosts = $costs->filter(fn ($cost) =>
            $this->isTransportCost($cost) && $this->isValidPaymentDocument($cost)
        );
        $otherOrUnsupportedCosts = $costs->reject(fn ($cost) =>
            $this->isTransportCost($cost) && $this->isValidPaymentDocument($cost)
        );
        $freight = $operationalTransportCosts;
        $other = $otherOrUnsupportedCosts;
        $freightTotal = round((float) $freight->sum('amount'), 2);
        $withoutReceiptTotal = round((float) $withoutReceipt->sum('amount'), 2);
        $otherTotal = round((float) $other->sum('amount'), 2);
        $linkedTotal = round($freightTotal + $otherTotal, 2);

        $saleBase = $mode === self::MODE_WITH_IGV && $order->affect_igv ? round($saleTotal / 1.18, 2) : $saleTotal;
        $saleIgv = round($saleTotal - $saleBase, 2);
        $purchaseBase = round((float) $supplierItems->sum(fn ($item) => $mode === self::MODE_WITH_IGV && $item->order_affect_igv ? (float) ($item->taxable_base ?: ((float) $item->line_total / 1.18)) : (float) $item->line_total), 2);
        $purchaseIgv = round($purchaseTotal - $purchaseBase, 2);
        $freightBase = $mode === self::MODE_WITH_IGV ? round((float) $freight->sum(fn ($cost) => $this->costHasIgv($cost) ? (float) $cost->amount / 1.18 : (float) $cost->amount), 2) : $freightTotal;
        $freightIgv = round($freightTotal - $freightBase, 2);
        $figures = $this->profitFigures($saleBase, $purchaseBase, $freightBase, $otherTotal);
        ['gross' => $gross, 'operating' => $operating, 'incomeTax' => $incomeTax, 'net' => $net] = $figures;
        $denominator = $mode === self::MODE_WITH_IGV ? $purchaseTotal + $freightBase + $otherTotal : $purchaseTotal + $freightTotal + $otherTotal;
        $percentage = $denominator > 0 ? round(($net / $denominator) * 100, 2) : 0.0;

        $customerItemByArticle = $order->items->where('status', '!=', 'deleted')->keyBy('article_id');
        $supplierItems->each(function ($item) use ($customerItemByArticle) {
            if (! $item->customer_purchase_order_item_id) {
                $item->customer_purchase_order_item_id = $customerItemByArticle->get($item->article_id)?->id;
            }
        });
        $purchasedByItem = $supplierItems->filter->customer_purchase_order_item_id->groupBy('customer_purchase_order_item_id')->map->sum('quantity');
        $enteredBySupplierItem = DB::table('warehouse_entry_items')->whereIn('supplier_purchase_order_item_id', $supplierItems->pluck('id'))->where('status', '!=', 'deleted')->groupBy('supplier_purchase_order_item_id')->selectRaw('supplier_purchase_order_item_id, SUM(quantity) total')->pluck('total', 'supplier_purchase_order_item_id');
        $enteredByCustomerItem = $supplierItems->groupBy('customer_purchase_order_item_id')->map(fn ($rows) => $rows->sum(fn ($row) => (float) ($enteredBySupplierItem[$row->id] ?? 0)));
        $warnings = [];
        if ($supplierItems->isEmpty()) $warnings[] = 'Esta OC aún no tiene compras a proveedor vinculadas.';
        if ($supplierItems->pluck('order_currency_id')->push($order->currency_id)->filter()->unique()->count() > 1) $warnings[] = 'La OC tiene documentos en monedas diferentes. Se requiere tipo de cambio para un cálculo exacto.';
        if ($net < 0) $warnings[] = 'La orden presenta utilidad negativa.';

        return compact('mode', 'order', 'supplierItems', 'supplierOrderIds', 'entryIds', 'costs', 'operationalTransportCosts', 'otherOrUnsupportedCosts', 'saleTotal', 'saleBase', 'saleIgv', 'purchaseTotal', 'purchaseBase', 'purchaseIgv', 'freightTotal', 'freightBase', 'freightIgv', 'withoutReceiptTotal', 'otherTotal', 'linkedTotal', 'gross', 'operating', 'incomeTax', 'net', 'percentage', 'purchasedByItem', 'enteredByCustomerItem', 'warnings') + [
            'igvRate' => self::IGV_RATE, 'incomeTaxRate' => self::INCOME_TAX_RATE,
            'igvSales' => $saleIgv, 'igvPurchases' => round($purchaseIgv + $freightIgv, 2), 'igvDifference' => round($saleIgv - $purchaseIgv - $freightIgv, 2),
        ];
    }

    public function saveSnapshot(array $data): CustomerOrderProfitabilityAnalysis
    {
        $userId = Auth::id();
        return CustomerOrderProfitabilityAnalysis::updateOrCreate(
            ['customer_purchase_order_id' => $data['order']->id, 'calculation_mode' => $data['mode']],
            ['currency_id' => $data['order']->currency_id, 'igv_rate' => $data['igvRate'], 'income_tax_rate' => $data['incomeTaxRate'], 'sale_total' => $data['saleTotal'], 'sale_base' => $data['saleBase'], 'sale_igv' => $data['saleIgv'], 'purchase_total' => $data['purchaseTotal'], 'purchase_base' => $data['purchaseBase'], 'purchase_igv' => $data['purchaseIgv'], 'freight_total' => $data['freightTotal'], 'freight_base' => $data['freightBase'], 'freight_igv' => $data['freightIgv'], 'expenses_without_receipt_total' => $data['withoutReceiptTotal'], 'other_expenses_total' => $data['otherTotal'], 'linked_costs_total' => $data['linkedTotal'], 'gross_profit' => $data['gross'], 'operating_profit' => $data['operating'], 'estimated_income_tax' => $data['incomeTax'], 'net_profit' => $data['net'], 'profitability_percentage' => $data['percentage'], 'igv_sales' => $data['igvSales'], 'igv_purchases' => $data['igvPurchases'], 'igv_difference' => $data['igvDifference'], 'warnings' => $data['warnings'], 'calculated_by' => $userId, 'calculated_at' => now(), 'created_by' => $userId, 'updated_by' => $userId, 'status' => 'ACTIVE']
        );
    }

    private function isValidPaymentDocument($cost): bool
    {
        return in_array(strtoupper(trim((string) data_get($cost, 'document_type'))), ['FACTURA', 'BOLETA'], true);
    }

    private function isTransportCost($cost): bool
    {
        $category = strtolower(trim((string) (data_get($cost, 'expense_category') ?: data_get($cost, 'category') ?: data_get($cost, 'group'))));
        if (in_array($category, ['freight_transport', 'transporte', 'transport'], true)) {
            return true;
        }

        $type = strtolower(trim((string) (data_get($cost, 'expense_type') ?: data_get($cost, 'cost_type') ?: data_get($cost, 'type'))));

        return in_array($type, [
            'agency_freight', 'flete_agencia', 'pickup_transfer', 'recojo_traslado',
            'transport_agency', 'agency_pickup_to_warehouse', 'agency_direct_to_warehouse',
            'supplier_warehouse_pickup', 'transfer_to_agency', 'courier', 'truck', 'mobility',
            'shipping', 'delivery', 'transfer', 'flete', 'transporte', 'traslado', 'movilidad',
        ], true);
    }

    private function costHasIgv($cost): bool { return in_array(strtoupper((string) $cost->document_type), ['FACTURA'], true); }

    private function profitFigures(float $saleBase, float $purchaseBase, float $freightBase, float $otherTotal): array
    {
        $gross = round($saleBase - $purchaseBase, 2);
        $operating = round($gross - $freightBase, 2);
        $incomeTax = round(max($operating, 0) * (self::INCOME_TAX_RATE / 100), 2);
        $net = round($operating - $incomeTax - $otherTotal, 2);

        return compact('gross', 'operating', 'incomeTax', 'net');
    }
}

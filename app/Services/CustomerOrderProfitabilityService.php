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

    public const IGV_RATE = WarehouseEntryExpense::IGV_RATE;

    public const INCOME_TAX_RATE = 29.5;

    public function calculate(CustomerPurchaseOrder $order, string $mode = self::MODE_WITHOUT_IGV): array
    {
        $order->loadMissing(['customer', 'company', 'currency', 'documents.documentType', 'items.article', 'items.unit', 'items.presentation', 'items.brand']);
        $usesIgvStructure = (bool) $order->affect_igv;
        $mode = $usesIgvStructure ? self::MODE_WITH_IGV : self::MODE_WITHOUT_IGV;
        $itemIds = $order->items->where('status', '!=', 'deleted')->pluck('id');
        $directSupplierOrderIds = DB::table('supplier_purchase_orders')
            ->where('customer_purchase_order_id', $order->id)
            ->whereNull('deleted_at')->where('status', '!=', 'cancelled')->pluck('id');
        $supplierItems = DB::table('supplier_purchase_order_items as items')
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'items.supplier_purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'orders.supplier_id')
            ->leftJoin('currencies as purchase_currencies', 'purchase_currencies.id', '=', 'orders.currency_id')
            ->leftJoin('currencies as payment_currencies', 'payment_currencies.id', '=', 'orders.payment_currency_id')
            ->where(function ($query) use ($itemIds, $directSupplierOrderIds) {
                $query->whereIn('items.customer_purchase_order_item_id', $itemIds)
                    ->orWhereIn('items.supplier_purchase_order_id', $directSupplierOrderIds);
            })
            ->whereNull('orders.deleted_at')->where('orders.status', '!=', 'cancelled')->where('items.status', '!=', 'deleted')
            ->select(
                'items.*',
                'orders.code as order_code',
                'orders.affect_igv as order_affect_igv',
                'orders.currency_id as order_currency_id',
                'orders.payment_currency_id as order_payment_currency_id',
                'orders.apply_exchange_rate as order_apply_exchange_rate',
                'orders.exchange_rate as order_exchange_rate',
                'orders.grand_total as order_grand_total',
                'orders.total_payment_currency as order_total_payment_currency',
                'orders.total_pen as order_total_pen',
                'orders.status as order_status',
                'orders.created_at as order_date',
                'suppliers.business_name as supplier_name',
                'purchase_currencies.code as purchase_currency_code',
                'purchase_currencies.symbol as purchase_currency_symbol',
                'payment_currencies.code as payment_currency_code',
                'payment_currencies.symbol as payment_currency_symbol'
            )
            ->get();
        $warehousePurchaseAmounts = DB::table('warehouse_entry_items as entry_items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'entry_items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as purchase_items', 'purchase_items.id', '=', 'entry_items.supplier_purchase_order_item_id')
            ->whereIn('entry_items.supplier_purchase_order_item_id', $supplierItems->pluck('id'))
            ->whereNull('entries.deleted_at')
            ->where('entries.status', 'registered')
            ->where('entry_items.status', '!=', 'deleted')
            ->groupBy('entry_items.supplier_purchase_order_item_id', 'purchase_items.supplier_purchase_order_id')
            ->selectRaw('entry_items.supplier_purchase_order_item_id, purchase_items.supplier_purchase_order_id')
            ->selectRaw('SUM(entry_items.subtotal) as subtotal, SUM(entry_items.tax_amount) as igv, SUM(entry_items.line_total) as total')
            ->selectRaw('SUM(CASE WHEN entries.affect_igv = 1 THEN entry_items.subtotal ELSE entry_items.line_total END) as accounting_base')
            ->selectRaw('SUM(CASE WHEN entries.affect_igv = 1 THEN entry_items.line_total ELSE 0 END) as affected_total')
            ->selectRaw('SUM(CASE WHEN entries.affect_igv = 1 THEN 0 ELSE entry_items.line_total END) as unaffected_total')
            ->get()
            ->keyBy('supplier_purchase_order_item_id');
        $supplierOrdersWithEntries = $warehousePurchaseAmounts
            ->pluck('supplier_purchase_order_id')
            ->map(fn ($id) => (int) $id)
            ->unique();
        $supplierItems->each(function ($item) use ($warehousePurchaseAmounts, $supplierOrdersWithEntries, $usesIgvStructure) {
            $factor = $this->supplierPurchasePenFactor($item);
            $useWarehouseAmounts = $supplierOrdersWithEntries->contains((int) $item->supplier_purchase_order_id);
            $warehouseAmount = $warehousePurchaseAmounts->get($item->id);
            $sourceAmounts = $this->purchaseSourceAmounts($item, $warehouseAmount, $useWarehouseAmounts);

            $item->pen_conversion_factor = $factor;
            $item->line_total_pen = round($sourceAmounts['total'] * $factor, 6);
            $item->taxable_base_pen = round($sourceAmounts['subtotal'] * $factor, 6);
            $item->purchase_affected_total_pen = round($sourceAmounts['affected_total'] * $factor, 6);
            $item->purchase_unaffected_total_pen = round($sourceAmounts['unaffected_total'] * $factor, 6);
            $item->unit_price_pen = round((float) $item->unit_price * $factor, 4);
            $item->purchase_amount_source = $sourceAmounts['source'];
            $this->applyConsideredPurchaseAmounts($item, $usesIgvStructure);
        });
        $supplierOrderIds = $supplierItems->pluck('supplier_purchase_order_id')->unique();
        $entryIds = DB::table('warehouse_entries as entries')
            ->leftJoin('warehouse_entry_items as items', 'items.warehouse_entry_id', '=', 'entries.id')
            ->where(function ($query) use ($supplierItems, $supplierOrderIds) {
                $query->whereIn('items.supplier_purchase_order_item_id', $supplierItems->pluck('id'))
                    ->orWhereIn('entries.supplier_purchase_order_id', $supplierOrderIds);
            })->whereNull('entries.deleted_at')->where('entries.status', 'registered')
            ->pluck('entries.id')->unique();
        $costs = WarehouseEntryExpense::query()->with(['documents', 'warehouseEntry:id,entry_number,document_date'])
            ->whereIn('warehouse_entry_id', $entryIds)
            ->where('status', 'ACTIVE')
            ->where(function ($query) {
                $query->whereNull('approval_status')
                    ->orWhereNotIn('approval_status', [
                        WarehouseEntryExpense::APPROVAL_REJECTED,
                        'rechazado',
                    ]);
            })
            ->get();

        $activeSaleItems = $order->items->where('status', '!=', 'deleted');
        [
            'total' => $saleTotal,
            'base' => $saleBase,
            'igv' => $saleIgv,
            'considered' => $saleValue,
            'profitability' => $saleProfitValue,
        ] = $this->saleAmounts($order, $activeSaleItems, $usesIgvStructure);
        $purchaseTotal = round((float) $supplierItems->sum('line_total_pen'), 2);
        $purchaseBase = round((float) $supplierItems->sum('taxable_base_pen'), 2);
        $purchaseIgv = round($purchaseTotal - $purchaseBase, 2);
        $purchaseValue = $purchaseTotal;
        $purchaseProfitValue = $this->purchaseProfitabilityValue($supplierItems, $usesIgvStructure);
        $withoutReceipt = $costs->reject(fn ($cost) => $this->hasOfficialDocument($cost));
        ['freight' => $operationalTransportCosts, 'other' => $otherOrUnsupportedCosts] = $this->classifyLinkedCosts($costs);
        $freight = $operationalTransportCosts;
        $other = $otherOrUnsupportedCosts;
        $linkedFigures = $this->linkedCostFigures($freight, $other, $usesIgvStructure);
        $freightTotal = $linkedFigures['freightTotal'];
        $freightBase = $linkedFigures['freightBase'];
        $freightIgv = $linkedFigures['freightIgv'];
        $freightValue = $linkedFigures['freightValue'];
        $otherGrossTotal = $linkedFigures['otherGrossTotal'];
        $otherBase = $linkedFigures['otherBase'];
        $otherIgv = $linkedFigures['otherIgv'];
        $otherTotal = $linkedFigures['otherValue'];
        $linkedGrossTotal = $linkedFigures['linkedGrossTotal'];
        $linkedBase = $linkedFigures['linkedBase'];
        $linkedIgv = $linkedFigures['linkedIgv'];
        $linkedProfitValue = $linkedFigures['linkedValue'];
        $linkedTotal = $linkedGrossTotal;
        $withoutReceiptTotal = round((float) $withoutReceipt->sum(fn ($cost) => $this->costValueForStructure($cost, $usesIgvStructure)), 2);
        $figures = $this->profitFigures($saleProfitValue, $purchaseProfitValue, $freightValue, $otherTotal);
        ['gross' => $gross, 'operating' => $operating, 'incomeTax' => $incomeTax, 'net' => $net] = $figures;
        ['base' => $profitabilityBase, 'percentage' => $percentage] = $this->profitabilityMetrics(
            $usesIgvStructure,
            $purchaseValue,
            $purchaseProfitValue,
            $saleIgv,
            $purchaseIgv,
            $freightValue,
            $otherTotal,
            $net
        );
        [
            'igvSales' => $igvSales,
            'igvPurchases' => $igvPurchases,
            'igvOfficialCosts' => $igvOfficialCosts,
            'igvPayable' => $igvPayable,
            'igvCreditBalance' => $igvCreditBalance,
            'totalTaxes' => $totalTaxes,
        ] = $this->taxFigures(
            $usesIgvStructure,
            $saleTotal,
            $saleProfitValue,
            $purchaseTotal,
            $purchaseProfitValue,
            $freightTotal,
            $freightValue,
            $incomeTax
        );

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
        if ($supplierItems->isEmpty()) {
            $warnings[] = 'Esta OC aún no tiene compras a proveedor vinculadas.';
        }
        $ordersWithoutPenConversion = $supplierItems
            ->filter(fn ($item) => strtoupper((string) $item->purchase_currency_code) !== 'PEN' && (float) $item->pen_conversion_factor <= 0)
            ->pluck('order_code')->filter()->unique()->values();
        if ($ordersWithoutPenConversion->isNotEmpty()) {
            $warnings[] = 'No se incluyeron en el total las compras extranjeras sin conversión a soles: '.$ordersWithoutPenConversion->implode(', ').'.';
        }
        if ($net < 0) {
            $warnings[] = 'La orden presenta utilidad negativa.';
        }

        return compact('mode', 'usesIgvStructure', 'order', 'supplierItems', 'supplierOrderIds', 'entryIds', 'costs', 'operationalTransportCosts', 'otherOrUnsupportedCosts', 'saleTotal', 'saleBase', 'saleIgv', 'saleValue', 'saleProfitValue', 'purchaseTotal', 'purchaseBase', 'purchaseIgv', 'purchaseValue', 'purchaseProfitValue', 'freightTotal', 'freightBase', 'freightIgv', 'freightValue', 'withoutReceiptTotal', 'otherGrossTotal', 'otherBase', 'otherIgv', 'otherTotal', 'linkedGrossTotal', 'linkedBase', 'linkedIgv', 'linkedTotal', 'linkedProfitValue', 'gross', 'operating', 'incomeTax', 'net', 'profitabilityBase', 'percentage', 'igvSales', 'igvPurchases', 'igvOfficialCosts', 'igvPayable', 'igvCreditBalance', 'totalTaxes', 'purchasedByItem', 'enteredByCustomerItem', 'warnings') + [
            'igvRate' => self::IGV_RATE, 'incomeTaxRate' => self::INCOME_TAX_RATE,
            'igvLinkedCosts' => $igvOfficialCosts,
            'igvDifference' => $igvPayable,
        ];
    }

    public function saveSnapshot(array $data): CustomerOrderProfitabilityAnalysis
    {
        $userId = Auth::id();
        $penCurrencyId = DB::table('currencies')->whereRaw('UPPER(code) = ?', ['PEN'])->value('id');

        return CustomerOrderProfitabilityAnalysis::updateOrCreate(
            ['customer_purchase_order_id' => $data['order']->id, 'calculation_mode' => $data['mode']],
            ['currency_id' => $penCurrencyId ?: $data['order']->currency_id, 'igv_rate' => $data['igvRate'], 'income_tax_rate' => $data['incomeTaxRate'], 'sale_total' => $data['saleTotal'], 'sale_base' => $data['saleBase'], 'sale_igv' => $data['saleIgv'], 'purchase_total' => $data['purchaseTotal'], 'purchase_base' => $data['purchaseBase'], 'purchase_igv' => $data['purchaseIgv'], 'freight_total' => $data['freightTotal'], 'freight_base' => $data['freightBase'], 'freight_igv' => $data['freightIgv'], 'expenses_without_receipt_total' => $data['withoutReceiptTotal'], 'other_expenses_total' => $data['otherTotal'], 'linked_costs_total' => $data['linkedTotal'], 'gross_profit' => $data['gross'], 'operating_profit' => $data['operating'], 'estimated_income_tax' => $data['incomeTax'], 'net_profit' => $data['net'], 'profitability_percentage' => $data['percentage'], 'igv_sales' => $data['igvSales'], 'igv_purchases' => $data['igvPurchases'], 'igv_difference' => $data['igvDifference'], 'warnings' => $data['warnings'], 'calculated_by' => $userId, 'calculated_at' => now(), 'created_by' => $userId, 'updated_by' => $userId, 'status' => 'ACTIVE']
        );
    }

    private function hasOfficialDocument($cost): bool
    {
        return WarehouseEntryExpense::isOfficialDocument(data_get($cost, 'document_type'));
    }

    private function classifyLinkedCosts($costs): array
    {
        $official = collect($costs)->filter(fn ($cost) => $this->hasOfficialDocument($cost))->values();

        return [
            'freight' => $official,
            'other' => collect($costs)->reject(fn ($cost) => $this->hasOfficialDocument($cost))->values(),
        ];
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

    private function costHasIgv($cost): bool
    {
        return filter_var(data_get($cost, 'affects_igv', false), FILTER_VALIDATE_BOOLEAN)
            && $this->hasOfficialDocument($cost);
    }

    private function saleAmounts(CustomerPurchaseOrder $order, $activeSaleItems, ?bool $usesIgvStructure = null): array
    {
        $usesIgvStructure ??= (bool) $order->affect_igv;
        $itemsTotal = round((float) collect($activeSaleItems)->sum(
            fn ($item) => (float) ($item->line_total ?: ((float) $item->quantity * (float) $item->unit_price))
        ), 2);
        $saleTotal = $order->grand_total !== null
            ? round((float) $order->grand_total, 2)
            : $itemsTotal;

        if (! $order->affect_igv) {
            return [
                'total' => $saleTotal,
                'base' => $saleTotal,
                'igv' => 0.0,
                'considered' => $saleTotal,
                'profitability' => $saleTotal,
            ];
        }

        $storedBase = round((float) $order->subtotal_taxed + (float) $order->subtotal_exonerated, 2);
        $itemsBase = round((float) collect($activeSaleItems)->sum(function ($item) {
            $total = (float) ($item->line_total ?: ((float) $item->quantity * (float) $item->unit_price));

            return (float) ($item->subtotal ?: ($total / (1 + (self::IGV_RATE / 100))));
        }), 2);
        $saleBase = $storedBase > 0 ? $storedBase : $itemsBase;
        $storedIgv = round((float) $order->igv, 2);
        $saleIgv = $storedIgv > 0 ? $storedIgv : round($saleTotal - $saleBase, 2);

        return [
            'total' => $saleTotal,
            'base' => $saleBase,
            'igv' => $saleIgv,
            'considered' => $saleTotal,
            'profitability' => $this->profitabilityAmount($saleTotal, true, $usesIgvStructure),
        ];
    }

    private function costTaxBreakdown($cost): array
    {
        $storedTotal = data_get($cost, 'total_amount');
        $total = round((float) (($storedTotal !== null && (float) $storedTotal > 0)
            ? $storedTotal
            : data_get($cost, 'amount', 0)), 2);
        if (! $this->costHasIgv($cost)) {
            return ['total' => $total, 'base' => $total, 'igv' => 0.0];
        }

        $rate = (float) data_get($cost, 'igv_rate', self::IGV_RATE);
        $rate = $rate > 0 ? $rate : self::IGV_RATE;
        $storedBase = data_get($cost, 'taxable_amount');
        $base = $storedBase !== null && (float) $storedBase > 0
            ? round((float) $storedBase, 2)
            : round($total / (1 + ($rate / 100)), 2);
        $storedIgv = data_get($cost, 'igv_amount');
        $igv = $storedIgv !== null
            ? round((float) $storedIgv, 2)
            : round($total - $base, 2);

        return ['total' => $total, 'base' => $base, 'igv' => $igv];
    }

    private function costValueForStructure($cost, bool $usesIgvStructure): float
    {
        $breakdown = $this->costTaxBreakdown($cost);

        return $this->profitabilityAmount(
            $breakdown['total'],
            $this->costHasIgv($cost),
            $usesIgvStructure,
            (float) data_get($cost, 'igv_rate', self::IGV_RATE)
        );
    }

    private function linkedCostFigures($freight, $other, bool $usesIgvStructure): array
    {
        collect($freight)->concat($other)->each(function ($cost) use ($usesIgvStructure) {
            $value = $this->costValueForStructure($cost, $usesIgvStructure);

            if (method_exists($cost, 'setAttribute')) {
                $cost->setAttribute('profitability_amount', $value);
            } else {
                $cost->profitability_amount = $value;
            }
        });

        $sum = function ($costs, string $field): float {
            return round((float) $costs->sum(fn ($cost) => $this->costTaxBreakdown($cost)[$field]), 2);
        };

        $freightTotal = $sum($freight, 'total');
        $freightBase = $sum($freight, 'base');
        $freightIgv = $sum($freight, 'igv');
        $otherGrossTotal = $sum($other, 'total');
        $otherBase = $sum($other, 'base');
        $otherIgv = $sum($other, 'igv');
        $freightValue = round((float) $freight->sum(fn ($cost) => $this->costValueForStructure($cost, $usesIgvStructure)), 6);
        // Los documentos no oficiales se descuentan completos después de la renta.
        $otherValue = round($otherGrossTotal, 6);

        return [
            'freightTotal' => $freightTotal,
            'freightBase' => $freightBase,
            'freightIgv' => $freightIgv,
            'freightValue' => $freightValue,
            'otherGrossTotal' => $otherGrossTotal,
            'otherBase' => $otherBase,
            'otherIgv' => $otherIgv,
            'otherValue' => $otherValue,
            'linkedGrossTotal' => round($freightTotal + $otherGrossTotal, 2),
            'linkedBase' => round($freightBase + $otherBase, 2),
            'linkedIgv' => round($freightIgv + $otherIgv, 2),
            'linkedValue' => round($freightValue + $otherValue, 6),
        ];
    }

    private function profitabilityAmount(
        float $total,
        bool $affectsIgv,
        bool $usesIgvStructure,
        float $igvRate = self::IGV_RATE
    ): float {
        if (! $usesIgvStructure || ! $affectsIgv) {
            return round($total, 6);
        }

        $igvRate = $igvRate > 0 ? $igvRate : self::IGV_RATE;

        return round($total / (1 + ($igvRate / 100)), 6);
    }

    private function purchaseProfitabilityValue($supplierItems, bool $usesIgvStructure): float
    {
        $affectedTotal = (float) collect($supplierItems)->sum('purchase_affected_total_pen');
        $unaffectedTotal = (float) collect($supplierItems)->sum('purchase_unaffected_total_pen');

        return round(
            $this->profitabilityAmount($affectedTotal, true, $usesIgvStructure) + $unaffectedTotal,
            6
        );
    }

    private function profitFigures(float $saleValue, float $purchaseValue, float $freightValue, float $otherValue): array
    {
        $grossValue = $saleValue - $purchaseValue;
        $operatingValue = $grossValue - $freightValue;
        $incomeTaxValue = max($operatingValue, 0) * (self::INCOME_TAX_RATE / 100);
        $netValue = $operatingValue - $incomeTaxValue - $otherValue;
        $gross = round($grossValue, 2);
        $operating = round($operatingValue, 2);
        $incomeTax = round($incomeTaxValue, 2);
        $net = round($netValue, 2);

        return compact('gross', 'operating', 'incomeTax', 'net');
    }

    private function profitabilityMetrics(
        bool $usesIgvStructure,
        float $purchaseConsidered,
        float $purchaseProfitValue,
        float $salesIgv,
        float $purchasesIgv,
        float $officialCosts,
        float $otherExpenses,
        float $netProfit
    ): array {
        $purchaseForBase = $usesIgvStructure ? $purchaseConsidered : $purchaseProfitValue;
        $salesPurchasesIgvDifference = $usesIgvStructure
            ? round($salesIgv - $purchasesIgv, 2)
            : 0.0;
        $profitabilityBase = round(
            $purchaseForBase + $salesPurchasesIgvDifference + $officialCosts + $otherExpenses,
            2
        );
        $profitabilityPercentage = $profitabilityBase > 0
            ? round(($netProfit / $profitabilityBase) * 100, 2)
            : 0.0;

        return [
            'base' => $profitabilityBase,
            'percentage' => $profitabilityPercentage,
        ];
    }

    private function taxFigures(
        bool $usesIgvStructure,
        float $saleTotal,
        float $saleProfitValue,
        float $purchaseTotal,
        float $purchaseProfitValue,
        float $officialCostsTotal,
        float $officialCostsValue,
        float $incomeTax
    ): array {
        $igvSales = $usesIgvStructure ? round($saleTotal - $saleProfitValue, 2) : 0.0;
        $igvPurchases = $usesIgvStructure ? round($purchaseTotal - $purchaseProfitValue, 2) : 0.0;
        $igvOfficialCosts = $usesIgvStructure ? round($officialCostsTotal - $officialCostsValue, 2) : 0.0;
        $igvPayable = round($igvSales - $igvPurchases - $igvOfficialCosts, 2);
        $igvCreditBalance = $igvPayable < 0 ? abs($igvPayable) : 0.0;
        $totalTaxes = round($incomeTax + max($igvPayable, 0), 2);

        return compact(
            'igvSales',
            'igvPurchases',
            'igvOfficialCosts',
            'igvPayable',
            'igvCreditBalance',
            'totalTaxes'
        );
    }

    private function supplierPurchasePenFactor(object $item): float
    {
        $purchaseTotal = (float) data_get($item, 'order_grand_total', 0);
        $totalPen = (float) data_get($item, 'order_total_pen', 0);
        if ($purchaseTotal > 0 && $totalPen > 0) {
            return $totalPen / $purchaseTotal;
        }

        if (strtoupper((string) data_get($item, 'purchase_currency_code')) === 'PEN') {
            return 1.0;
        }

        $paymentTotal = (float) data_get($item, 'order_total_payment_currency', 0);
        if ($purchaseTotal > 0
            && strtoupper((string) data_get($item, 'payment_currency_code')) === 'PEN'
            && $paymentTotal > 0) {
            return $paymentTotal / $purchaseTotal;
        }

        $exchangeRate = (float) data_get($item, 'order_exchange_rate', 0);

        return $exchangeRate > 0 ? $exchangeRate : 0.0;
    }

    private function applyConsideredPurchaseAmounts(object $item, bool $usesIgvStructure = false): void
    {
        $item->purchase_subtotal_pen = round((float) $item->taxable_base_pen, 2);
        $item->purchase_total_pen = round((float) $item->line_total_pen, 2);
        $item->purchase_igv_pen = round($item->purchase_total_pen - $item->purchase_subtotal_pen, 2);
        $item->considered_purchase_amount = $item->purchase_total_pen;
        $affectsIgv = (bool) ($item->order_affect_igv ?? false);
        $affectedTotal = (float) ($item->purchase_affected_total_pen ?? ($affectsIgv ? $item->purchase_total_pen : 0));
        $unaffectedTotal = (float) ($item->purchase_unaffected_total_pen ?? ($affectsIgv ? 0 : $item->purchase_total_pen));
        $item->profitability_purchase_amount = round(
            $this->profitabilityAmount($affectedTotal, true, $usesIgvStructure) + $unaffectedTotal,
            6
        );
    }

    private function purchaseSourceAmounts(object $item, ?object $warehouseAmount, bool $useWarehouseAmounts): array
    {
        if ($useWarehouseAmounts) {
            $total = (float) ($warehouseAmount?->total ?? 0);

            return [
                'subtotal' => (float) ($warehouseAmount?->accounting_base ?? $warehouseAmount?->subtotal ?? 0),
                'total' => $total,
                'affected_total' => (float) ($warehouseAmount?->affected_total ?? 0),
                'unaffected_total' => (float) ($warehouseAmount?->unaffected_total ?? 0),
                'source' => 'warehouse_entry',
            ];
        }

        $total = (float) ($item->total_with_igv
            ?? $item->line_total
            ?: ((float) $item->quantity * (float) $item->unit_price));

        return [
            'subtotal' => (bool) ($item->order_affect_igv ?? false)
                ? (float) ($item->taxable_base ?? $item->subtotal ?? ($total / 1.18))
                : $total,
            'total' => $total,
            'affected_total' => (bool) ($item->order_affect_igv ?? false) ? $total : 0.0,
            'unaffected_total' => (bool) ($item->order_affect_igv ?? false) ? 0.0 : $total,
            'source' => 'supplier_purchase_order',
        ];
    }
}

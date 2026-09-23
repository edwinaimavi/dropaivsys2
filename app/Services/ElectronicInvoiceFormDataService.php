<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerPurchaseOrder;
use App\Models\ElectronicInvoiceSeries;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Quote;
use App\Models\SunatCatalogItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

class ElectronicInvoiceFormDataService
{
    public function get(array $catalogs = []): array
    {
        $authorizedCompanyIds = Auth::check()
            ? Auth::user()->companies()->pluck('companies.id')
            : null;

        return [
            'companies' => $catalogs['companies'] ?? Company::query()
                ->where('status', true)
                ->orderBy('business_name')
                ->get(),
            'customers' => $catalogs['customers'] ?? Customer::query()
                ->where('status', true)
                ->orderBy('business_name')
                ->orderBy('full_name')
                ->get(),
            'customerBranches' => CustomerBranch::query()
                ->where('status', true)
                ->orderByDesc('is_main')
                ->orderBy('branch_name')
                ->get(['id', 'customer_id', 'branch_name', 'address']),
            'currencies' => $catalogs['currencies'] ?? Currency::query()
                ->where('status', 'ACTIVE')
                ->orderBy('description')
                ->get(),
            'series' => ElectronicInvoiceSeries::query()
                ->where('status', 'ACTIVE')
                ->where('environment', 'internal')
                ->orderBy('document_type')
                ->orderBy('serie')
                ->get(),
            'companyEnvironments' => ElectronicInvoiceSetting::query()
                ->where('is_active', true)
                ->where('environment', 'internal')
                ->whereNotNull('company_id')
                ->get(['company_id', 'environment'])
                ->groupBy('company_id')
                ->map(fn ($settings) => $settings->first()->environment),
            'articles' => $catalogs['articles'] ?? Article::query()
                ->with('unit.sunatUnit')
                ->where('status', 'ACTIVE')
                ->orderBy('billing_name')
                ->get(['id', 'code', 'billing_name', 'commercial_name', 'unit_id', 'presentation_id', 'brand_id']),
            'quotes' => $catalogs['quotes'] ?? Quote::query()
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'quote_number', 'customer_id']),
            'customerPurchaseOrders' => CustomerPurchaseOrder::query()
                ->whereIn('status', ['partial_entered', 'entered', 'attended', 'delivered'])
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'code', 'purchase_order_number', 'quote_id', 'customer_id', 'customer_branch_id', 'siaf_file_number', 'process_type']),
            'warehouses' => Warehouse::query()
                ->where('status', 'ACTIVE')
                ->whereHas('companyWarehouses', fn ($query) => $query
                    ->where('is_active', true)
                    ->when($authorizedCompanyIds !== null, fn ($query) => $query
                        ->whereIn('company_id', $authorizedCompanyIds)))
                ->with(['companyWarehouses' => fn ($query) => $query
                    ->where('is_active', true)
                    ->when($authorizedCompanyIds !== null, fn ($query) => $query
                        ->whereIn('company_id', $authorizedCompanyIds))
                    ->select(['id', 'company_id', 'warehouse_id'])])
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'taxAffectations' => SunatCatalogItem::activeTaxAffectations(),
        ];
    }
}

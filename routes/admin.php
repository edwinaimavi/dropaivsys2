<?php


use App\Http\Controllers\Admin\ArticleController;

use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerBranchContactController;
use App\Http\Controllers\Admin\CustomerController;

use App\Http\Controllers\Admin\PresentationController;

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;

use App\Http\Controllers\Admin\SupplierAccountController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierPurchaseOrderController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Http\Controllers\Admin\CustomerBranchController;
use App\Http\Controllers\Admin\CustomerPurchaseOrderController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ElectronicInvoiceApiLogController;
use App\Http\Controllers\Admin\ElectronicInvoiceCatalogController;
use App\Http\Controllers\Admin\ElectronicInvoiceController;
use App\Http\Controllers\Admin\ElectronicInvoiceSeriesController;
use App\Http\Controllers\Admin\ElectronicInvoiceSettingController;
use App\Http\Controllers\Admin\KardexController;
use App\Http\Controllers\Admin\MarketStudyComparisonController;
use App\Http\Controllers\Admin\MarketStudyController;
use App\Http\Controllers\Admin\MarketStudyQuoteController;
use App\Http\Controllers\Admin\QuoteController;
use Illuminate\Support\Facades\Route;
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');


//Rutas para la gestión de usuarios en el panel de administración|
Route::get('users/list', [UserController::class, 'list'])->name('users.list');
Route::resource('users', UserController::class)->except(['create', 'show']);

Route::get('roles/list', [RoleController::class, 'list'])->name('roles.list');
Route::get('roles/{role}/permissions', [RoleController::class, 'getPermissions'])->name('roles.permissions');
Route::resource('roles', RoleController::class)->except(['create', 'show']);



//RUTAS PARA CLIENTES 
Route::get('customers/list', [CustomerController::class, 'list'])->name('customers.list');
Route::get(
    'customers/search-ubigeo',
    [CustomerController::class, 'searchUbigeo']
)->name('customers.searchUbigeo');

Route::get(
    'customer-branches/list/{customer}',
    [CustomerBranchController::class, 'list']
)->name('customer-branches.list');

Route::post(
    'customer-branches',
    [CustomerBranchController::class, 'store']
)->name('customer-branches.store');

Route::put(
    'customer-branches/{branch}',
    [CustomerBranchController::class, 'update']
)->name('customer-branches.update');

Route::delete(
    'customer-branches/{branch}',
    [CustomerBranchController::class, 'destroy']
)->name('customer-branches.destroy');
Route::get('customers/consultar/{numero}', [CustomerController::class, 'consultarDocumento'])
    ->name('customers.consultar');


Route::get(
    'customer-branch-contacts/list/{branch}',
    [CustomerBranchContactController::class, 'list']
)->name('customer-branch-contacts.list');

Route::post(
    'customer-branch-contacts',
    [CustomerBranchContactController::class, 'store']
)->name('customer-branch-contacts.store');

Route::put(
    'customer-branch-contacts/{contact}',
    [CustomerBranchContactController::class, 'update']
)->name('customer-branch-contacts.update');

Route::delete(
    'customer-branch-contacts/{contact}',
    [CustomerBranchContactController::class, 'destroy']
)->name('customer-branch-contacts.destroy');

Route::get(
    'customer-branches/by-customer/{customer}',
    [CustomerBranchController::class, 'branchesByCustomer']
)->name('customer-branches.byCustomer');

/* Route::get('clients/document/{dniruc}', [ClientController::class, 'consultarDniRuc'])
    ->name('clients.consultarDniRuc'); */

Route::resource('customers', CustomerController::class)->except(['create', 'show']);


//RUTAS PARA CATEGORIAS
Route::get('categories/list', [CategoryController::class, 'list'])->name('categories.list');

Route::get(
    'admin/categories/generate-code',
    [CategoryController::class, 'generateCode']
)->name('categories.generateCode');

Route::resource('categories', CategoryController::class)->except(['create']);

// ======================================================
// RUTAS PARA SUBCATEGORÍAS
// ======================================================

Route::get(
    'categories/{category}/subcategories',
    [CategoryController::class, 'subcategoryList']
)->name('categories.subcategories.list');

Route::post(
    'categories/subcategories/store',
    [CategoryController::class, 'storeSubcategory']
)->name('categories.subcategories.store');

Route::put(
    'categories/subcategories/{subcategory}',
    [CategoryController::class, 'updateSubcategory']
)->name('categories.subcategories.update');

Route::delete(
    'categories/subcategories/{subcategory}',
    [CategoryController::class, 'destroySubcategory']
)->name('categories.subcategories.delete');

//RUTAS PARA UNIDADES DE MEDIDA 
Route::get('units/list', [UnitController::class, 'list'])->name('units.list');
Route::get(
    'units/search',
    [UnitController::class, 'search']
)->name('units.search');
Route::resource('units', UnitController::class)->except(['create', 'show']);

//RUTAS PARA PRESENTACIONES
Route::get('presentations/list', [PresentationController::class, 'list'])->name('presentations.list');
Route::get(
    'presentations/search',
    [PresentationController::class, 'search']
)->name('presentations.search');
Route::resource('presentations', PresentationController::class)->except(['create', 'show']);

//RUTAS PARA PROVEEDORES 
Route::get(
    'suppliers/search-ubigeo',
    [SupplierController::class, 'searchUbigeo']
)->name('suppliers.searchUbigeo');
Route::get('suppliers/list', [SupplierController::class, 'list'])->name('suppliers.list');
Route::get(
    'suppliers/consultar-ruc/{numero}',
    [SupplierController::class, 'consultarRuc']
)->name('suppliers.consultarRuc');
Route::resource('suppliers', SupplierController::class)->except(['create', 'show']);

//RUTAS PARA CUENTAS BANCARIAS DE PROVEEDORES
Route::get(
    'supplier-accounts/list/{supplier}',
    [SupplierAccountController::class, 'list']
)->name('supplier-accounts.list');

Route::post(
    'supplier-accounts',
    [SupplierAccountController::class, 'store']
)->name('supplier-accounts.store');

Route::put(
    'supplier-accounts/{supplierAccount}',
    [SupplierAccountController::class, 'update']
)->name('supplier-accounts.update');

Route::delete(
    'supplier-accounts/{supplierAccount}',
    [SupplierAccountController::class, 'destroy']
)->name('supplier-accounts.destroy');

Route::get(
    'suppliers/{supplier}/accounts',
    [SupplierController::class, 'accounts']
)->name('suppliers.accounts');


//RUTAS PARA MARCAS 
Route::get('brands/list', [BrandController::class, 'list'])->name('brands.list');

Route::get(
    'brands/generate-code',
    [BrandController::class, 'generateCode']
)->name('brands.generateCode');

Route::get('brands/search', [BrandController::class, 'search'])->name('brands.search');

Route::resource('brands', BrandController::class)->except(['create', 'show']);


//RUTAS PARA ARTÍCULOS
Route::get('articles/list', [ArticleController::class, 'list'])->name('articles.list');
Route::get(
    'articles/generate-code',
    [ArticleController::class, 'generateCode']
)->name('articles.generateCode');

Route::get(
    'articles/subcategories/{category}',
    [ArticleController::class, 'getSubcategories']
)->name('articles.subcategories');

Route::get(
    'articles/{article}/show-data',
    [ArticleController::class, 'showData']
)->name('articles.showData');


Route::get(
    'articles/list-picker',
    [ArticleController::class, 'listPicker']
)->name('articles.listPicker');

Route::resource('articles', ArticleController::class)->except(['create', 'show']);


//RUTAS PARA ESTUDIOS DE MERCADO
Route::get('market-studies/list', [MarketStudyController::class, 'list'])->name('market-studies.list');
Route::get(
    'market-studies/generate-code',
    [MarketStudyController::class, 'generateCode']
)->name('market-studies.generateCode');

Route::get(
    'market-study-quotes/generate-number',
    [MarketStudyQuoteController::class, 'generateNumber']
)->name('market-study-quotes.generateNumber');

Route::get(
    'market-study-quotes/suppliers',
    [MarketStudyQuoteController::class, 'suppliers']
)->name('market-study-quotes.suppliers');

Route::get(
    'market-study-quotes/currencies',
    [MarketStudyQuoteController::class, 'currencies']
)->name('market-study-quotes.currencies');

Route::get(
    'market-study-quotes/exchange-rate',
    [MarketStudyQuoteController::class, 'exchangeRate']
)->name('market-study-quotes.exchange-rate');

Route::get(
    'market-study-quotes/supplier/{supplier}',
    [MarketStudyQuoteController::class, 'supplierDetail']
)->name('market-study-quotes.supplier-detail');

Route::get(
    'market-study-quotes/study-items/{marketStudy}',
    [MarketStudyQuoteController::class, 'studyItems']
)->name('market-study-quotes.study-items');

Route::get(
    'market-studies/{marketStudy}',
    [MarketStudyController::class, 'show']
)->name('market-studies.show');
Route::resource('market-studies', MarketStudyController::class)->except(['create']);

Route::get(
    'market-studies/{id}/quotes',
    [MarketStudyQuoteController::class, 'listByStudy']
)->name('market-study-quotes.list');

// RUTAS PARA COTIZACIONES DE ESTUDIO DE MERCADO
Route::resource('market-study-quotes', MarketStudyQuoteController::class)
    ->except(['create', 'show']);


//RUTAS PARA COMPRACION DE COTIZACION
Route::get(
    'market-study-comparisons/{marketStudy}',
    [MarketStudyComparisonController::class, 'show']
)->name('market-study-comparisons.show');


Route::post(
    '/admin/market-study/{marketStudy}/comparison/save',
    [MarketStudyComparisonController::class, 'save']
)->name('market-study-comparison.save');


//RUTAS PARA COTIZACIONES 
Route::get('quotes/generate-number', [QuoteController::class, 'generateNumber'])
    ->name('quotes.generateNumber');

Route::get('quotes/list', [QuoteController::class, 'list'])->name('quotes.list');

Route::get('quotes/customer/{customer}/branches', [QuoteController::class, 'customerBranches'])
    ->name('quotes.customerBranches');

Route::get('quotes/market-study/{marketStudy}/winners', [QuoteController::class, 'marketStudyWinners'])
    ->name('quotes.marketStudyWinners');

Route::resource('quotes', QuoteController::class)->except(['create', 'show']);

// RUTAS PARA ÓRDENES DE COMPRA DE CLIENTES
Route::get(
    'customer-purchase-orders/list',
    [CustomerPurchaseOrderController::class, 'list']
)->name('customer-purchase-orders.list');

Route::get(
    'customer-purchase-orders/generate-code',
    [CustomerPurchaseOrderController::class, 'generateCode']
)->name('customer-purchase-orders.generateCode');

Route::get(
    'customer-purchase-orders/quote/{quote}/items',
    [CustomerPurchaseOrderController::class, 'quoteItems']
)->name('customer-purchase-orders.quoteItems');

Route::get(
    'customer-purchase-orders/customer/{customer}/branches',
    [CustomerPurchaseOrderController::class, 'customerBranches']
)->name('customer-purchase-orders.customerBranches');

Route::resource(
    'customer-purchase-orders',
    CustomerPurchaseOrderController::class
)->except(['create']);

// RUTAS PARA ORDENES DE COMPRA A PROVEEDORES
Route::get(
    'supplier-purchase-orders/list',
    [SupplierPurchaseOrderController::class, 'list']
)->name('supplier-purchase-orders.list');

Route::get(
    'supplier-purchase-orders/generate-code',
    [SupplierPurchaseOrderController::class, 'generateCode']
)->name('supplier-purchase-orders.generateCode');

Route::get(
    'supplier-purchase-orders/supplier/{supplier}/accounts',
    [SupplierPurchaseOrderController::class, 'supplierAccounts']
)->name('supplier-purchase-orders.supplierAccounts');

Route::get(
    'supplier-purchase-orders/customer-purchase-order/{customerPurchaseOrder}/items',
    [SupplierPurchaseOrderController::class, 'customerPurchaseOrderItems']
)->name('supplier-purchase-orders.customerPurchaseOrderItems');

Route::post(
    'supplier-purchase-orders/load-customer-order-items',
    [SupplierPurchaseOrderController::class, 'loadCustomerOrderItems']
)->name('supplier-purchase-orders.loadCustomerOrderItems');

Route::resource(
    'supplier-purchase-orders',
    SupplierPurchaseOrderController::class
)->except(['create']);

// RUTAS PARA INGRESOS DE ALMACEN
Route::get(
    'warehouse-entries/list',
    [WarehouseEntryController::class, 'list']
)->name('warehouse-entries.list');

Route::get(
    'warehouse-entries/generate-number',
    [WarehouseEntryController::class, 'generateNumber']
)->name('warehouse-entries.generateNumber');

Route::post(
    'warehouse-entries/load-supplier-order-items',
    [WarehouseEntryController::class, 'loadSupplierPurchaseOrderItems']
)->name('warehouse-entries.loadSupplierOrderItems');

Route::get(
    'warehouse-entries/{warehouseEntry}/documents/{document}/download',
    [WarehouseEntryController::class, 'downloadDocument']
)->name('warehouse-entries.documents.download');

Route::delete(
    'warehouse-entries/{warehouseEntry}/documents/{document}',
    [WarehouseEntryController::class, 'destroyDocument']
)->name('warehouse-entries.documents.destroy');

Route::get(
    'warehouse-entries/{warehouseEntry}/pdf',
    [WarehouseEntryController::class, 'pdf']
)->name('warehouse-entries.pdf');

Route::resource(
    'warehouse-entries',
    WarehouseEntryController::class
)->except(['create']);

// RUTAS PARA FACTURACION ELECTRONICA
Route::get('electronic-invoices/list', [ElectronicInvoiceController::class, 'list'])
    ->name('electronic-invoices.list');
Route::get('electronic-invoices/{electronicInvoice}/pdf', [ElectronicInvoiceController::class, 'pdf'])
    ->name('electronic-invoices.pdf');
Route::get('electronic-invoices/{electronicInvoice}/payload', [ElectronicInvoiceController::class, 'previewPayload'])
    ->name('electronic-invoices.payload');
Route::post('electronic-invoices/{electronicInvoice}/send', [ElectronicInvoiceController::class, 'sendToApi'])
    ->name('electronic-invoices.send');
Route::resource('electronic-invoices', ElectronicInvoiceController::class)->except(['create']);

Route::get('electronic-invoice-settings/list', [ElectronicInvoiceSettingController::class, 'list'])
    ->name('electronic-invoice-settings.list');
Route::resource('electronic-invoice-settings', ElectronicInvoiceSettingController::class)
    ->only(['index', 'store', 'show', 'update']);

Route::get('electronic-invoice-series/list', [ElectronicInvoiceSeriesController::class, 'list'])
    ->name('electronic-invoice-series.list');
Route::get('electronic-invoice-series/next-number', [ElectronicInvoiceSeriesController::class, 'getNextNumber'])
    ->name('electronic-invoice-series.nextNumber');
Route::resource('electronic-invoice-series', ElectronicInvoiceSeriesController::class)
    ->except(['create', 'edit']);

Route::get('sunat-catalogs/list', [ElectronicInvoiceCatalogController::class, 'list'])
    ->name('sunat-catalogs.list');
Route::get('sunat-catalogs', [ElectronicInvoiceCatalogController::class, 'index'])
    ->name('sunat-catalogs.index');

Route::get('electronic-invoice-api-logs/list', [ElectronicInvoiceApiLogController::class, 'list'])
    ->name('electronic-invoice-api-logs.list');
Route::get('electronic-invoice-api-logs/{electronicInvoiceApiLog}', [ElectronicInvoiceApiLogController::class, 'show'])
    ->name('electronic-invoice-api-logs.show');

// RUTAS PARA KARDEX DE ALMACEN
Route::get('kardex/list', [KardexController::class, 'list'])->name('kardex.list');
Route::get('kardex/stock/list', [KardexController::class, 'stock'])->name('kardex.stock');
Route::get('kardex/article/{article}/history', [KardexController::class, 'articleHistory'])
    ->name('kardex.article-history');
Route::get('kardex/{movement}', [KardexController::class, 'show'])->name('kardex.show');
Route::get('kardex', [KardexController::class, 'index'])->name('kardex.index');

@extends('layouts.app')

@section('subtitle', 'Órdenes de Compra a Proveedores')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-truck-loading text-success"></i>
                    &Oacute;rdenes de Compra a Proveedores
                </h1>
                <small class="text-muted">
                    Gesti&oacute;n de compras realizadas a proveedores.
                </small>
            </div>

            @can('admin.supplier-purchase-orders.store')
            <button id="btnCreateSupplierPurchaseOrder" class="btn btn-success shadow-sm px-4" type="button">
                <i class="fas fa-plus-circle mr-1"></i>
                Nueva Orden
            </button>
            @endcan
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-decoration-none">
                        <i class="fas fa-house-user"></i>
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item active">&Oacute;rdenes de Compra a Proveedores</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-success"></i>
                Lista de &Oacute;rdenes de Compra
            </h5>
            <small class="text-muted">Compras registradas para proveedores</small>
        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableSupplierPurchaseOrder" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>C&Oacute;DIGO</th>
                            <th>PROVEEDOR</th>
                            <th>EMPRESA</th>
                            <th>MONEDA</th>
                            <th>TOTAL</th>
                            <th>ESTADO</th>
                            <th>F. REGISTRO</th>
                            <th width="150">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.supplier-purchase-orders.partials.modal')
    @include('admin.supplier-purchase-orders.partials.quickSupplierModal')
    @include('admin.supplier-purchase-orders.partials.quickSupplierAccountModal')
    @include('admin.supplier-purchase-orders.partials.viewModal')
    @include('admin.supplier-purchase-orders.partials.trackingModal')
@stop

@push('css')
    @vite('resources/css/supplier-purchase-order-tracking.css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableSupplierPurchaseOrder thead th {
            padding: 14px 10px;
            border: 0 !important;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableSupplierPurchaseOrder tbody td {
            padding: 12px 8px;
            border-top: 1px solid #f1f1f1;
            font-size: 13px;
            vertical-align: middle !important;
        }

        #tableSupplierPurchaseOrder tbody tr:hover {
            background: #fafafa;
        }

        .supplier-document-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
            flex-wrap: nowrap;
        }

        .supplier-name-document-link {
            display: inline-flex;
            min-width: 0;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border: 1px solid #bde9ff;
            border-radius: 999px;
            background: #e8f7ff;
            color: #087ea4;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.2;
            text-decoration: none;
            text-align: left;
            white-space: normal;
            word-break: break-word;
        }

        .supplier-name-document-link i.fa-file-pdf {
            flex: 0 0 auto;
            color: #ef4444;
            font-size: 12px;
        }

        .supplier-name-document-link:hover {
            background: #dff3ff;
            color: #075985;
            text-decoration: none;
        }

        .supplier-document-download-link {
            display: inline-flex;
            flex: 0 0 26px;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border: 1px solid #cbd5e1;
            border-radius: 50%;
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            text-decoration: none;
        }

        .supplier-document-download-link:hover {
            background: #e2e8f0;
            color: #0f172a;
            text-decoration: none;
        }

        .supplier-name-text {
            color: #1f2937;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .supplier-order-side-card {
            background: #f8faf9;
        }

        .supplier-order-side-icon,
        .supplier-order-view-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f5ee;
            color: #198754;
            font-size: 22px;
        }

        .supplier-order-view-header {
            background: #198754;
        }

        .supplier-order-view-total {
            font-size: 22px;
            font-weight: 700;
            color: #198754;
        }

        .supplier-order-items-table th,
        .supplier-order-items-table td {
            white-space: nowrap;
            vertical-align: middle !important;
        }

        .supplier-order-items-table input,
        .supplier-order-items-table select {
            min-width: 120px;
        }

        .supplier-order-items-table .item-billing-name {
            min-width: 240px;
        }

        .breadcrumb {
            margin-bottom: 0;
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            supplierPurchaseOrderList: "{{ route('admin.supplier-purchase-orders.list') }}",
            supplierPurchaseOrderStore: "{{ route('admin.supplier-purchase-orders.store') }}",
            supplierPurchaseOrderUpdate: "{{ url('admin/supplier-purchase-orders') }}",
            supplierPurchaseOrderDelete: "{{ url('admin/supplier-purchase-orders') }}",
            supplierPurchaseOrderShow: "{{ url('admin/supplier-purchase-orders') }}",
            supplierPurchaseOrderGenerateCode: "{{ route('admin.supplier-purchase-orders.generateCode') }}",
            supplierPurchaseOrderSupplierAccounts: "{{ url('admin/supplier-purchase-orders/supplier/:id/accounts') }}",
            supplierPurchaseOrderLoadCustomerItems: "{{ route('admin.supplier-purchase-orders.customerOrderItems') }}",
            supplierOrderShippingAgencyBranches: "{{ url('admin/shipping-agencies/:id/branches') }}",
            supplierOrderShippingAgencyContacts: "{{ url('admin/shipping-agencies/:id/contacts') }}",
            supplierOrderShippingBranchContacts: "{{ url('admin/shipping-agency-branches/:id/contacts') }}",
            supplierQuickStore: "{{ route('admin.suppliers.quick-store-with-account') }}",
            supplierQuickAccountStore: "{{ url('admin/suppliers/:id/quick-account') }}",
            supplierQuickByRuc: "{{ url('admin/suppliers/by-ruc') }}",
            supplierQuickConsultarRuc: "{{ url('admin/suppliers/consultar-ruc') }}",
            supplierQuickSearchUbigeo: "{{ route('admin.suppliers.searchUbigeo') }}",
            supplierPurchaseOrderTrackings: "{{ url('admin/supplier-purchase-orders') }}",
            supplierPurchaseOrderTrackingEvents: "{{ url('admin/supplier-purchase-order-trackings') }}"
        });
        window.spoTrackingPermissions = {
            destroy: @json(auth()->user()->can('admin.supplier-purchase-orders.trackings.destroy'))
        };
    </script>

    @vite(['resources/js/pages/supplier-purchase-order.js', 'resources/js/pages/supplier-purchase-order-tracking.js'])
@endpush

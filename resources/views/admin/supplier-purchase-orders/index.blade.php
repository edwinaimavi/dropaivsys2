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

        .supplier-doc-section {
            overflow: hidden;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
        }

        .supplier-doc-section-header {
            gap: 12px;
            padding: 15px 17px;
            border: 0;
            border-bottom: 1px solid #edf1f5;
            background: linear-gradient(110deg, #fff 0%, #f8fafc 68%, #eefbf6 100%);
        }

        .supplier-doc-section-header h6 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0f172a !important;
        }

        .supplier-doc-section-icon {
            display: inline-grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border-radius: 9px;
            background: #dcfce7;
            color: #07845f;
            font-size: 12px;
        }

        .supplier-doc-section-header small {
            display: block;
            margin-top: 3px;
            padding-left: 38px;
            font-size: 10px;
        }

        .supplier-doc-add {
            padding: 7px 12px;
            border: 1px solid #9edbc7;
            border-radius: 9px;
            background: #fff;
            color: #08765a;
            font-size: 11px;
            font-weight: 800;
            transition: all .18s ease;
        }

        .supplier-doc-add:hover {
            border-color: #10b981;
            background: #ecfdf5;
            color: #047857;
            transform: translateY(-1px);
        }

        .supplier-doc-section-body {
            padding: 14px 16px 13px;
            background: #fbfcfd;
        }

        .supplier-doc-list:empty {
            display: none;
        }

        .supplier-doc-list + .supplier-doc-list:not(:empty) {
            margin-top: 10px;
        }

        .supplier-doc-existing {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 8px;
            padding: 10px 11px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .supplier-doc-existing:hover {
            border-color: #cbd5e1;
            box-shadow: 0 5px 14px rgba(15, 23, 42, .05);
        }

        .supplier-doc-existing-icon {
            display: grid;
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            place-items: center;
            border-radius: 10px;
            font-size: 15px;
        }

        .supplier-doc-existing-icon.is-pdf {
            background: #fff1f2;
            color: #e11d48;
        }

        .supplier-doc-existing-icon.is-image {
            background: #eff6ff;
            color: #2563eb;
        }

        .supplier-doc-existing-info {
            min-width: 0;
            flex: 1;
        }

        .supplier-doc-existing-info strong,
        .supplier-doc-existing-info small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .supplier-doc-existing-info strong {
            color: #1e293b;
            font-size: 12px;
        }

        .supplier-doc-existing-info small {
            margin-top: 2px;
            color: #64748b;
            font-size: 10px;
        }

        .supplier-doc-existing-actions {
            display: flex;
            flex: 0 0 auto;
            gap: 6px;
        }

        .supplier-doc-action {
            display: inline-grid;
            width: 29px;
            height: 29px;
            padding: 0;
            place-items: center;
            border: 1px solid;
            border-radius: 8px;
            background: #fff;
            font-size: 10px;
            text-decoration: none;
            transition: all .18s ease;
        }

        .supplier-doc-action.is-open {
            border-color: #bfdbfe;
            color: #2563eb;
        }

        .supplier-doc-action.is-delete {
            border-color: #fecdd3;
            color: #e11d48;
        }

        .supplier-doc-action:hover {
            text-decoration: none;
            transform: translateY(-1px);
        }

        .supplier-doc-action.is-open:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .supplier-doc-action.is-delete:hover {
            background: #fff1f2;
            color: #be123c;
        }

        .supplier-doc-row {
            margin-bottom: 9px;
            padding: 11px 12px 12px;
            border: 1px solid #dfe7ec;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 5px 14px rgba(15, 23, 42, .035);
        }

        .supplier-doc-row-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 9px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eef2f5;
        }

        .supplier-doc-row-header > div {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .supplier-doc-row-header strong {
            color: #334155;
            font-size: 11px;
        }

        .supplier-doc-row-icon {
            color: #059669;
            font-size: 11px;
        }

        .supplier-doc-row-grid {
            display: grid;
            grid-template-columns: minmax(180px, .8fr) minmax(210px, 1fr) minmax(260px, 1.35fr);
            gap: 11px;
            align-items: start;
        }

        .supplier-doc-row label {
            margin-bottom: 4px;
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .supplier-doc-control {
            height: 37px;
            border-color: #dbe4e9;
            border-radius: 9px;
            color: #334155;
            font-size: 11px;
        }

        .supplier-doc-control:focus {
            border-color: #76cdb2;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .08);
        }

        .supplier-doc-file-label {
            display: inline-flex;
            width: 100%;
            height: 37px;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin: 0;
            border: 1px dashed #9ecabb;
            border-radius: 9px;
            background: #f4fbf8;
            color: #08765a !important;
            cursor: pointer;
            font-size: 10px !important;
            text-transform: none !important;
            transition: all .18s ease;
        }

        .supplier-doc-file-label:hover {
            border-color: #10b981;
            background: #ecfdf5;
        }

        .supplier-doc-file-name {
            display: block;
            overflow: hidden;
            margin-top: 4px;
            color: #94a3b8;
            font-size: 9px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .supplier-doc-file-name.has-file {
            color: #047857;
            font-weight: 700;
        }

        .supplier-doc-remove {
            display: grid;
            width: 25px;
            height: 25px;
            padding: 0;
            place-items: center;
            border: 1px solid #fecdd3;
            border-radius: 8px;
            background: #fff7f8;
            color: #e11d48;
            font-size: 9px;
            transition: all .18s ease;
        }

        .supplier-doc-remove:hover {
            border-color: #fda4af;
            background: #ffe4e6;
            color: #be123c;
        }

        .supplier-doc-section-help {
            margin-top: 8px;
            color: #8492a3;
            font-size: 9px;
        }

        @media (max-width: 991px) {
            .supplier-doc-row-grid {
                grid-template-columns: 1fr 1fr;
            }

            .supplier-doc-row-grid .form-group:last-child {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 575px) {
            .supplier-doc-section-header {
                align-items: flex-start !important;
            }

            .supplier-doc-section-header small {
                padding-left: 0;
            }

            .supplier-doc-add {
                width: 100%;
            }

            .supplier-doc-row-grid {
                grid-template-columns: 1fr;
            }

            .supplier-doc-row-grid .form-group:last-child {
                grid-column: auto;
            }

            .supplier-doc-existing {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .supplier-doc-existing-actions {
                width: 100%;
                justify-content: flex-end;
            }
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
            shippingAgencyStore: "{{ route('admin.shipping-agencies.store') }}",
            shippingAgencySearchRuc: "{{ url('admin/shipping-agencies/consultar-ruc') }}",
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

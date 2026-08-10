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
    <div class="card border-0 shadow-lg rounded-xl supplier-orders-list-card">
        <div class="card-header border-0 supplier-orders-list-header">
            <div class="d-flex align-items-center">
                <span class="supplier-orders-list-icon mr-3"><i class="fas fa-list"></i></span>
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark">Lista de &Oacute;rdenes de Compra</h5>
                    <small class="text-muted">Compras registradas para proveedores</small>
                </div>
            </div>
        </div>

        <div class="card-body supplier-orders-list-body">
            <div class="table-responsive supplier-orders-table-wrap">
                <table id="tableSupplierPurchaseOrder" class="table align-middle text-center w-100 supplier-orders-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>C&Oacute;DIGO</th>
                            <th>OC CLIENTE</th>
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

        .supplier-orders-list-card {
            overflow: visible;
            border: 1px solid #e7efeb !important;
            background: #fff;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .075) !important;
        }

        .supplier-orders-list-header {
            padding: 20px 22px 17px;
            border-bottom: 1px solid #e8f0ec !important;
            border-radius: 18px 18px 0 0 !important;
            background: linear-gradient(115deg, #fff 0%, #f8fbfa 60%, #eef9f4 100%);
        }

        .supplier-orders-list-icon {
            display: inline-grid;
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            place-items: center;
            border: 1px solid #cce9dd;
            border-radius: 12px;
            background: #eaf8f1;
            color: #16805e;
            box-shadow: 0 5px 13px rgba(22, 128, 94, .1);
        }

        .supplier-orders-list-body {
            padding: 18px 20px 20px;
        }

        .supplier-orders-table-wrap {
            border: 1px solid #e6eeea;
            border-radius: 13px;
            background: #fff;
        }

        #tableSupplierPurchaseOrder {
            margin-bottom: 0 !important;
            border-collapse: separate !important;
            border-spacing: 0;
        }

        #tableSupplierPurchaseOrder thead th {
            padding: 13px 10px;
            border: 0 !important;
            border-bottom: 1px solid #dbe9e2 !important;
            color: #496158;
            background: #f0f7f4;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .45px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        #tableSupplierPurchaseOrder thead th:first-child {
            border-radius: 11px 0 0;
        }

        #tableSupplierPurchaseOrder thead th:last-child {
            border-radius: 0 11px 0 0;
        }

        #tableSupplierPurchaseOrder tbody td {
            padding: 11px 9px;
            border: 0;
            border-bottom: 1px solid #edf2ef;
            color: #33443d;
            background: #fff;
            font-size: 12px;
            vertical-align: middle !important;
            transition: background-color .18s ease, box-shadow .18s ease;
        }

        #tableSupplierPurchaseOrder tbody tr:last-child td {
            border-bottom: 0;
        }

        #tableSupplierPurchaseOrder tbody tr:hover td {
            background: #f3faf7;
            box-shadow: inset 0 1px rgba(31, 138, 101, .04), inset 0 -1px rgba(31, 138, 101, .04);
        }

        #tableSupplierPurchaseOrder tbody tr:hover td:first-child {
            box-shadow: inset 3px 0 #63b795;
        }

        .supplier-order-deep-link-highlight td {
            animation: supplier-order-deep-link-pulse 1s ease-in-out 3;
        }

        @keyframes supplier-order-deep-link-pulse {
            0%, 100% { background: #fff; }
            50% { background: #dff6eb; box-shadow: inset 3px 0 #198754; }
        }

        #tableSupplierPurchaseOrder tbody td:nth-child(1),
        #tableSupplierPurchaseOrder tbody td:nth-child(2),
        #tableSupplierPurchaseOrder tbody td:nth-child(6),
        #tableSupplierPurchaseOrder tbody td:nth-child(7),
        #tableSupplierPurchaseOrder tbody td:nth-child(10) {
            color: #64746d;
        }

        #tableSupplierPurchaseOrder tbody td:nth-child(8) {
            text-align: right !important;
            color: #203c31;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        #tableSupplierPurchaseOrder tbody td:nth-child(9) .badge {
            min-width: 112px !important;
            padding: 6px 10px !important;
            border: 1px solid rgba(15, 23, 42, .07);
            box-shadow: none !important;
            font-size: 10px !important;
        }

        #tableSupplierPurchaseOrder tbody td:nth-child(9) .badge-primary,
        #tableSupplierPurchaseOrder tbody td:nth-child(9) .badge-info {
            border-color: #c8e5ee;
            background: #e9f6fa;
            color: #17677d;
        }

        #tableSupplierPurchaseOrder tbody td:nth-child(9) .badge-success {
            border-color: #c7e7d5;
            background: #eaf7ef;
            color: #176b45;
        }

        #tableSupplierPurchaseOrder tbody td:nth-child(9) .badge-warning {
            border-color: #f0dfaa;
            background: #fff7dc;
            color: #856414;
        }

        #tableSupplierPurchaseOrder tbody td:nth-child(9) .badge-danger {
            border-color: #efcdd1;
            background: #fcecee;
            color: #a43b45;
        }

        #tableSupplierPurchaseOrder.supplier-orders-table>thead,
        #tableSupplierPurchaseOrder tbody .supplier-order-source-row { display: none !important; }
        #tableSupplierPurchaseOrder tbody .supplier-order-accordion-row>td { padding: 7px 0 !important; border: 0 !important; background: #fff !important; }
        .supplier-order-accordion { --group-accent: #198754; --group-accent-dark: #176548; --group-border: #d8e9e3; --group-header: linear-gradient(110deg,#eff9f5,#f8fcfa); --group-header-open: #eaf7f2; --group-soft: #dcefe7; --group-chip: #f1f8f5; overflow: hidden; border: 1px solid var(--group-border); border-left: 4px solid var(--group-accent); border-radius: 0 12px 12px 0; background: #fff; box-shadow: 0 4px 14px color-mix(in srgb, var(--group-accent) 10%, transparent); }
        .supplier-order-group-header { display: grid; grid-template-columns: minmax(220px,1fr) minmax(180px,.8fr) minmax(390px,1.6fr); align-items: center; gap: 18px; padding: 12px 14px; background: var(--group-header); text-align: left; }
        .supplier-order-accordion.is-open .supplier-order-group-header { background: var(--group-header-open); }
        .supplier-order-group-identity { display: flex; min-width: 0; align-items: center; gap: 10px; }
        .supplier-order-group-icon { display: inline-grid; flex: 0 0 35px; width: 35px; height: 35px; place-items: center; border-radius: 9px; background: var(--group-soft); color: var(--group-accent-dark); }
        .supplier-order-group-identity small,
        .supplier-order-group-identity strong,
        .supplier-order-group-identity>div>span { display: block; }
        .supplier-order-group-identity small { color: #648076; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .supplier-order-group-identity strong { color: #204d3e; font-size: 13px; }
        .supplier-order-group-identity>div>span { max-width: 390px; overflow: hidden; color: #62746d; font-size: 10px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
        .supplier-order-group-branch { display: flex; min-width: 0; max-width: 100%; flex-direction: column; align-items: flex-start; justify-self: center; gap: 3px; padding: 7px 11px; border: 1px solid var(--group-border); border-radius: 10px; background: var(--group-chip); color: #47645b; box-shadow: 0 2px 7px rgba(44,112,88,.045); }
        .supplier-order-group-branch small { color: #789087; font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .supplier-order-group-branch span { display: flex; min-width: 0; align-items: center; gap: 6px; font-size: 10px; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; }
        .supplier-order-group-branch i { color: var(--group-accent); font-size: 10px; }
        .supplier-order-group-metrics { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
        .supplier-order-group-metrics>span { display: inline-flex; align-items: center; gap: 5px; padding: 5px 8px; border: 1px solid #dce8e3; border-radius: 999px; background: #fff; color: #60736b; font-size: 10px; font-weight: 700; white-space: nowrap; }
        .supplier-order-group-metrics i { color: var(--group-accent); font-size: 9px; }
        .supplier-order-group-total { color: #1c654c !important; font-weight: 800 !important; }
        .supplier-order-group-toggle { display: inline-flex; align-items: center; gap: 7px; padding: 6px 10px; border: 1px solid var(--group-accent); border-radius: 8px; background: #fff; color: var(--group-accent-dark); font-size: 10px; font-weight: 800; cursor: pointer; transition: .18s ease; }
        .supplier-order-group-toggle:hover,
        .supplier-order-group-toggle:focus { outline: 0; background: var(--group-accent); color: #fff; box-shadow: 0 3px 9px color-mix(in srgb, var(--group-accent) 22%, transparent); }
        .supplier-order-group-status { border-color: var(--group-border) !important; background: var(--group-soft) !important; color: var(--group-accent-dark) !important; font-weight: 800 !important; }
        .supplier-order-group--registered { --group-accent: #4b8fc9; --group-accent-dark: #2f6f9f; --group-border: #cfe1ef; --group-header: linear-gradient(110deg,#edf6fc,#f8fbfd); --group-header-open: #e7f2fa; --group-soft: #dcecf7; --group-chip: #f1f7fb; }
        .supplier-order-group--entered { --group-accent: #3b9870; --group-accent-dark: #236f50; --group-border: #cce6da; --group-header: linear-gradient(110deg,#edf8f3,#f8fcfa); --group-header-open: #e6f5ee; --group-soft: #d9eee4; --group-chip: #f0f8f4; }
        .supplier-order-group--mixed { --group-accent: #c49338; --group-accent-dark: #80601f; --group-border: #eadcb9; --group-header: linear-gradient(110deg,#fff9eb,#fffdf8); --group-header-open: #fbf2dc; --group-soft: #f5e9c9; --group-chip: #fdf8ec; }
        .supplier-order-group--cancelled { --group-accent: #b06a70; --group-accent-dark: #85454b; --group-border: #ead2d4; --group-header: linear-gradient(110deg,#fbf1f2,#fdfafa); --group-header-open: #f7e9ea; --group-soft: #f1dcde; --group-chip: #fbf3f4; }
        .supplier-order-group-body { padding: 0 13px 13px; border-top: 1px solid #dcebe6; }
        .supplier-order-group-table-wrap { overflow-x: auto; padding-top: 11px; }
        .supplier-order-group-table { width: 100%; min-width: 900px; border-collapse: separate; border-spacing: 0; }
        #tableSupplierPurchaseOrder .supplier-order-group-table th { padding: 8px 9px; border: 0; border-bottom: 2px solid #d7e8e2; background: #f5f9f7; color: #516a61; font-size: 10px; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
        #tableSupplierPurchaseOrder .supplier-order-group-table td { padding: 8px 9px !important; border: 0 !important; border-bottom: 1px solid #edf2f0 !important; background: #fff !important; color: #495752 !important; font-size: 11px !important; white-space: nowrap; box-shadow: none !important; }
        #tableSupplierPurchaseOrder .supplier-order-group-table tbody tr:last-child td { border-bottom: 0 !important; }
        #tableSupplierPurchaseOrder .supplier-order-group-table tbody tr:hover td { background: #f9fbfa !important; }
        .supplier-order-group-actions { min-width: 120px; text-align: center; }

        @media (max-width: 991.98px) {
            .supplier-order-group-header { grid-template-columns: 1fr; align-items: flex-start; gap: 10px; }
            .supplier-order-group-branch { width: 100%; justify-self: stretch; }
            .supplier-order-group-metrics { justify-content: flex-start; }
        }

        .supplier-order-code-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 8px 5px 6px;
            border: 1px solid transparent;
            border-radius: 9px;
            background: transparent;
            color: #147553;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: color .18s ease, background-color .18s ease, border-color .18s ease, transform .18s ease;
        }

        .supplier-order-code-link:hover,
        .supplier-order-code-link:focus {
            border-color: #c8e7da;
            background: #edf9f4;
            color: #0e6244;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .supplier-order-code-icon {
            display: inline-grid;
            width: 23px;
            height: 23px;
            place-items: center;
            border-radius: 7px;
            background: #dff3ea;
            color: #16805e;
            font-size: 11px;
        }

        .customer-order-cell {
            min-width: 155px;
            margin: 2px 0;
            text-align: left;
            line-height: 1.2;
        }

        .customer-order-number {
            display: inline-block;
            padding: 3px 8px;
            border: 1px solid #d8e2ea;
            border-radius: 999px;
            background: #f2f7f5;
            color: #35564a;
            font-size: 11px;
            font-weight: 800;
            box-shadow: 0 2px 5px rgba(15, 23, 42, .04);
        }

        .customer-order-cell small {
            display: block;
            margin-top: 3px;
            color: #6c757d;
            font-size: 10px;
            font-weight: 600;
            white-space: normal;
        }

        .supplier-provider-quote-link {
            display: inline-flex;
            min-width: 0;
            max-width: 220px;
            align-items: center;
            gap: 7px;
            padding: 5px 9px 5px 6px;
            border: 1px solid #c8e7da;
            border-radius: 9px;
            background: #edf9f4;
            color: #147553;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
            text-decoration: none;
            text-align: left;
            white-space: normal;
            word-break: break-word;
            cursor: pointer;
            transition: color .18s ease, background-color .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .supplier-provider-quote-link:hover,
        .supplier-provider-quote-link:focus {
            border-color: #a9d8c5;
            background: #e3f5ed;
            color: #0e6244;
            box-shadow: 0 5px 12px rgba(20, 117, 83, .1);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .supplier-provider-quote-icon {
            display: inline-grid;
            flex: 0 0 23px;
            align-items: center;
            justify-content: center;
            width: 23px;
            height: 23px;
            border-radius: 7px;
            background: #d8f0e5;
            color: #16805e;
            font-size: 11px;
        }

        .supplier-provider-name {
            display: inline-block;
            max-width: 210px;
            padding: 5px 9px;
            border: 1px solid #e7ecea;
            border-radius: 9px;
            background: #fafcfb;
            color: #1f2937;
            font-weight: 700;
            overflow-wrap: anywhere;
            cursor: default;
        }

        .supplier-order-total {
            display: inline-block;
            min-width: 100px;
            padding: 6px 10px;
            border: 1px solid #d8e7e0;
            border-radius: 9px;
            background: #f5faf7;
            color: #203c31;
            box-shadow: 0 2px 6px rgba(15, 23, 42, .035);
            text-align: right;
        }

        #tableSupplierPurchaseOrder .dp-table-actions {
            justify-content: center;
            gap: 6px;
            flex-wrap: nowrap;
        }

        #tableSupplierPurchaseOrder .dp-action-main,
        #tableSupplierPurchaseOrder .dp-action-trigger {
            min-height: 31px;
            border-radius: 8px;
            box-shadow: 0 3px 8px rgba(15, 23, 42, .08);
            font-size: 11px;
            font-weight: 700;
            transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
        }

        #tableSupplierPurchaseOrder .dp-action-main:hover,
        #tableSupplierPurchaseOrder .dp-action-trigger:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(15, 23, 42, .13);
        }

        #tableSupplierPurchaseOrder .dp-action-trigger {
            border-color: #dce5e1 !important;
            background: #f8faf9;
            color: #53645d;
        }

        #tableSupplierPurchaseOrder_wrapper .dataTables_length,
        #tableSupplierPurchaseOrder_wrapper .dataTables_filter,
        #tableSupplierPurchaseOrder_wrapper .dataTables_info {
            color: #64746d;
            font-size: 12px;
        }

        #tableSupplierPurchaseOrder_wrapper .supplier-orders-toolbar {
            margin: 0 0 14px !important;
            padding: 12px 14px;
            border: 1px solid #e5ede9;
            border-radius: 11px;
            background: #f9fbfa;
        }

        #tableSupplierPurchaseOrder_wrapper .supplier-orders-footer {
            margin: 14px 0 0 !important;
            padding-top: 13px;
            border-top: 1px solid #edf1ef;
        }

        #tableSupplierPurchaseOrder_wrapper .dataTables_length select,
        #tableSupplierPurchaseOrder_wrapper .dataTables_filter input {
            height: 36px;
            border: 1px solid #d9e4df;
            border-radius: 9px;
            background: #fff;
            color: #33443d;
            box-shadow: 0 2px 7px rgba(15, 23, 42, .035);
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        #tableSupplierPurchaseOrder_wrapper .dataTables_filter input {
            width: min(270px, 70vw);
            margin-left: 8px;
            padding: 7px 12px 7px 34px;
        }

        #tableSupplierPurchaseOrder_wrapper .dataTables_filter label {
            position: relative;
            margin-bottom: 0;
        }

        #tableSupplierPurchaseOrder_wrapper .supplier-order-search-icon {
            position: absolute;
            z-index: 2;
            right: 238px;
            bottom: 11px;
            color: #819189;
            pointer-events: none;
        }

        #tableSupplierPurchaseOrder_wrapper .dataTables_filter input:focus,
        #tableSupplierPurchaseOrder_wrapper .dataTables_length select:focus {
            border-color: #75bda4;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(25, 135, 84, .1);
        }

        #tableSupplierPurchaseOrder_wrapper .pagination {
            gap: 4px;
        }

        #tableSupplierPurchaseOrder_wrapper .page-link {
            min-width: 32px;
            border: 1px solid #e1e9e5;
            border-radius: 8px;
            color: #52645c;
            text-align: center;
        }

        #tableSupplierPurchaseOrder_wrapper .page-item.active .page-link {
            border-color: #198754;
            background: #198754;
            color: #fff;
            box-shadow: 0 4px 10px rgba(25, 135, 84, .18);
        }

        #tableSupplierPurchaseOrder_wrapper .dt-buttons {
            display: inline-flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 7px;
            padding: 8px;
            border: 1px solid #e5ebe8;
            border-radius: 11px;
            background: #f8faf9;
        }

        #tableSupplierPurchaseOrder_wrapper .dt-buttons .btn {
            min-width: 92px;
            margin: 0 !important;
            padding: 7px 13px;
            border: 1px solid #334155;
            border-radius: 8px;
            background: #334155;
            color: #fff;
            box-shadow: 0 4px 10px rgba(15, 23, 42, .12);
            font-size: 11px;
            font-weight: 700;
            transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
        }

        #tableSupplierPurchaseOrder_wrapper .dt-buttons .btn:hover {
            border-color: #1f2937;
            background: #1f2937;
            transform: translateY(-1px);
            box-shadow: 0 6px 13px rgba(15, 23, 42, .17);
        }

        @media (max-width: 767.98px) {
            .supplier-orders-list-body {
                padding: 13px;
            }

            #tableSupplierPurchaseOrder_wrapper .dataTables_length,
            #tableSupplierPurchaseOrder_wrapper .dataTables_filter {
                margin-bottom: 10px;
                text-align: left !important;
            }

            #tableSupplierPurchaseOrder_wrapper .dataTables_filter input {
                width: 100%;
                margin: 6px 0 0;
            }

            #tableSupplierPurchaseOrder_wrapper .supplier-order-search-icon {
                right: auto;
                bottom: 11px;
                left: 12px;
            }

            #tableSupplierPurchaseOrder tbody td {
                white-space: normal;
            }

            #tableSupplierPurchaseOrder .dp-table-actions {
                justify-content: flex-start;
            }

            #tableSupplierPurchaseOrder_wrapper .supplier-orders-toolbar {
                padding: 11px;
            }

            .supplier-order-group-toggle { width: 100%; justify-content: center; }
        }

        #tableSupplierPurchaseOrder tbody tr.child td {
            padding: 13px 15px;
            background: #f8fbfa;
        }

        #tableSupplierPurchaseOrder tbody tr.child ul.dtr-details {
            width: 100%;
            margin: 0;
        }

        #tableSupplierPurchaseOrder tbody tr.child ul.dtr-details>li {
            padding: 8px 0;
            border-bottom: 1px solid #e6eeea;
        }

        #tableSupplierPurchaseOrder tbody tr.child .dtr-title {
            min-width: 105px;
            color: #557067;
            font-size: 10px;
            letter-spacing: .35px;
            text-transform: uppercase;
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
            shippingAgencySearchRuc: "{{ url('admin/document-lookup/ruc') }}",
            supplierQuickStore: "{{ route('admin.suppliers.quick-store-with-account') }}",
            supplierQuickAccountStore: "{{ url('admin/suppliers/:id/quick-account') }}",
            supplierQuickByRuc: "{{ url('admin/suppliers/by-ruc') }}",
            supplierQuickConsultarRuc: "{{ url('admin/document-lookup/ruc') }}",
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

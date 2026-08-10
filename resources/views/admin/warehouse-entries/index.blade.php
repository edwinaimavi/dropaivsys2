@extends('layouts.app')

@section('subtitle', 'Ingresos de Almacén')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-warehouse text-info"></i>
                    Ingresos de Almac&eacute;n
                </h1>
                <small class="text-muted">Registro f&iacute;sico y documental de mercader&iacute;a ingresada</small>
            </div>

            @can('admin.warehouse-entries.store')
            <button id="btnCreateWarehouseEntry" class="btn btn-info shadow-sm px-4" type="button">
                <i class="fas fa-plus-circle mr-1"></i>
                Nuevo Ingreso
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
                <li class="breadcrumb-item active">Ingresos de Almac&eacute;n</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-info"></i>
                Lista de ingresos
            </h5>
            <small class="text-muted">Mercader&iacute;a registrada como ingreso a almac&eacute;n</small>
        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableWarehouseEntry" class="table table-hover align-middle text-center w-100 warehouse-entry-accordion-table">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>N&deg; INGRESO</th>
                            <th>ORDEN COMPRA</th>
                            <th>OC CLIENTE</th>
                            <th>PROVEEDOR</th>
                            <th>EMPRESA</th>
                            <th>ALMAC&Eacute;N</th>
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

    @include('admin.warehouse-entries.partials.modal')
    @include('admin.warehouse-entries.partials.viewModal')
@stop

@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableWarehouseEntry thead th {
            padding: 14px 10px;
            border: 0 !important;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableWarehouseEntry tbody td {
            padding: 12px 8px;
            border-top: 1px solid #f1f1f1;
            font-size: 13px;
            vertical-align: middle !important;
        }

        #tableWarehouseEntry tbody tr:hover {
            background: #fafafa;
        }

        .warehouse-customer-order { min-width: 155px; margin: 2px 0; text-align: left; line-height: 1.2; }
        .warehouse-customer-order>span,
        .warehouse-customer-order-empty { display: inline-block; padding: 4px 8px; border: 1px solid #d8e5e1; border-radius: 999px; background: #f2f8f6; color: #31584c; font-size: 11px; font-weight: 800; }
        .warehouse-customer-order small { display: block; margin-top: 4px; color: #6b7974; font-size: 10px; font-weight: 600; white-space: normal; }
        .warehouse-customer-order .warehouse-customer-order-branch { color: #3f7262; }
        .warehouse-customer-order-empty { border-color: #e2e7e5; background: #f7f8f8; color: #87918d; font-weight: 600; }
        .warehouse-entry-customer-orders { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; margin-bottom: 15px; }
        .warehouse-entry-customer-order-card { padding: 11px 13px; border: 1px solid #d7e8e3; border-radius: 11px; background: #f4faf8; }
        .warehouse-entry-customer-order-card>span,
        .warehouse-entry-customer-order-card>strong,
        .warehouse-entry-customer-order-card>small { display: block; }
        .warehouse-entry-customer-order-card>span { margin-bottom: 5px; color: #71817b; font-size: 9px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .warehouse-entry-customer-order-card>strong { color: #195f49; font-size: 14px; }
        .warehouse-entry-customer-order-card>small { margin-top: 3px; color: #677772; }

        #tableWarehouseEntry.warehouse-entry-accordion-table>thead,
        #tableWarehouseEntry tbody .warehouse-entry-source-row {
            display: none !important;
        }

        #tableWarehouseEntry tbody .warehouse-entry-accordion-row td {
            padding: 7px 0;
            border: 0;
            background: #fff;
        }

        .warehouse-entry-accordion {
            overflow: hidden;
            border: 1px solid #d8e9e3;
            border-left: 4px solid #198754;
            border-radius: 0 12px 12px 0;
            background: #fff;
            box-shadow: 0 4px 14px rgba(25, 96, 72, .06);
        }

        .warehouse-entry-group-header {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(180px, .8fr) minmax(390px, 1.6fr);
            align-items: center;
            gap: 18px;
            padding: 11px 14px;
            background: linear-gradient(110deg, #eff9f5, #f8fcfa);
            text-align: left;
            transition: background-color .18s ease;
        }

        .warehouse-entry-accordion.is-open .warehouse-entry-group-header { background: #eaf7f2; }

        .warehouse-entry-group-identity {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 10px;
        }

        .warehouse-entry-group-icon {
            display: inline-grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 9px;
            background: #dcefe7;
            color: #187455;
        }

        .warehouse-entry-group-identity small,
        .warehouse-entry-group-identity strong,
        .warehouse-entry-group-identity>div>span {
            display: block;
        }

        .warehouse-entry-group-identity small {
            color: #648076;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .warehouse-entry-group-identity strong {
            color: #204d3e;
            font-size: 13px;
        }

        .warehouse-entry-group-identity>div>span {
            max-width: 420px;
            overflow: hidden;
            color: #62746d;
            font-size: 10px;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .warehouse-entry-group-branch {
            display: flex;
            min-width: 0;
            max-width: 100%;
            flex-direction: column;
            align-items: flex-start;
            justify-self: center;
            gap: 3px;
            padding: 7px 11px;
            border: 1px solid #d6e8e1;
            border-radius: 10px;
            background: #f1f8f5;
            color: #476f62;
            box-shadow: 0 2px 7px rgba(44, 112, 88, .045);
        }

        .warehouse-entry-group-branch small {
            color: #789087;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .warehouse-entry-group-branch span {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .warehouse-entry-group-branch i { flex: 0 0 auto; color: #36866b; font-size: 10px; }

        .warehouse-entry-group-metrics {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 6px;
        }

        .warehouse-entry-group-metrics>span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border: 1px solid #dce8e3;
            border-radius: 999px;
            background: #fff;
            color: #60736b;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .warehouse-entry-group-metrics i { color: #3b896d; font-size: 9px; }
        .warehouse-entry-group-metrics .warehouse-entry-group-total { color: #1c654c; font-weight: 800; }

        .warehouse-entry-group-toggle {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border: 1px solid #20805f;
            border-radius: 8px;
            background: #fff;
            color: #176548;
            font-size: 10px;
            font-weight: 800;
            cursor: pointer;
            transition: color .18s ease, background-color .18s ease, box-shadow .18s ease;
        }

        .warehouse-entry-group-toggle:hover,
        .warehouse-entry-group-toggle:focus {
            outline: 0;
            background: #198754;
            color: #fff;
            box-shadow: 0 3px 9px rgba(25, 135, 84, .2);
        }

        .warehouse-entry-group-body { padding: 0 13px 13px; border-top: 1px solid #dcebe6; }
        .warehouse-entry-group-table-wrap { overflow-x: auto; padding-top: 11px; }
        .warehouse-entry-group-table { width: 100%; min-width: 1050px; border-collapse: separate; border-spacing: 0; }
        .warehouse-entry-group-table th {
            padding: 8px 9px;
            border-bottom: 2px solid #d7e8e2;
            background: #f5f9f7;
            color: #516a61;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .warehouse-entry-group-table td {
            padding: 8px 9px !important;
            border-bottom: 1px solid #edf2f0 !important;
            background: #fff !important;
            color: #495752;
            font-size: 11px !important;
            white-space: nowrap;
        }
        .warehouse-entry-group-table tbody tr:last-child td { border-bottom: 0 !important; }
        .warehouse-entry-group-table tbody tr:hover td { background: #f9fbfa !important; }
        .warehouse-entry-group-actions { min-width: 110px; text-align: center; }

        @media (max-width: 991.98px) {
            .warehouse-entry-group-header { grid-template-columns: 1fr; align-items: flex-start; gap: 10px; }
            .warehouse-entry-group-branch { width: 100%; justify-self: stretch; }
            .warehouse-entry-group-metrics { justify-content: flex-start; }
            .warehouse-entry-group-identity>div>span { max-width: calc(100vw - 105px); }
        }

        @media (max-width: 767.98px) {
            .warehouse-entry-group-toggle { width: 100%; justify-content: center; }
        }

        .warehouse-entry-side-card {
            background: #fff;
            border-radius: 10px;
        }

        .warehouse-entry-side-icon,
        .warehouse-entry-view-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e6f6f4;
            color: #11867a;
            font-size: 18px;
        }

        .warehouse-entry-table-scroll {
            overflow-x: auto;
        }

        .warehouse-entry-items-table th,
        .warehouse-entry-items-table td {
            white-space: nowrap;
            vertical-align: middle !important;
        }

        .warehouse-entry-items-table input,
        .warehouse-entry-items-table select {
            min-width: 120px;
        }

        .warehouse-entry-items-table .item-billing-name-text {
            min-width: 240px;
        }

        .warehouse-entry-lots-cell {
            min-width: 190px;
            white-space: normal !important;
        }

        .warehouse-entry-lots-cell .btn {
            white-space: nowrap;
        }

        .warehouse-entry-lot-visual-row {
            background: #fbfdfd;
        }

        .warehouse-entry-lot-visual-row td {
            border-top: 1px dashed #dce8e7 !important;
            color: #56636b;
            font-size: 11px;
        }

        .warehouse-entry-lot-visual-row:hover {
            background: #f2faf8 !important;
        }

        .warehouse-entry-lot-badge {
            border: 1px solid #bfe2dc;
            border-radius: 10px;
            background: #eaf8f5;
            color: #11786f;
            font-size: 10px;
            font-weight: 700;
            padding: 5px 8px;
        }

        .warehouse-entry-lot-branch {
            color: #77aaa4;
            font-size: 11px;
        }

        .warehouse-entry-lot-repeat {
            display: inline-block;
            max-width: 240px;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        .warehouse-entry-lot-quantity-display {
            display: inline-block;
            min-width: 90px;
            padding: 5px 8px;
            border-radius: 6px;
            background: #edf9f4;
            text-align: right;
        }

        .warehouse-entry-lot-reference-total {
            color: #66727a !important;
            font-weight: 600;
        }

        #warehouseEntryModal {
            z-index: 2060 !important;
        }

        .warehouse-entry-backdrop-main {
            z-index: 2050 !important;
        }

        #warehouseEntryLotsModal,
        .warehouse-entry-lots-modal {
            z-index: 2080 !important;
        }

        .warehouse-entry-backdrop-lots {
            z-index: 2070 !important;
        }

        #warehouseEntryModal.show,
        #warehouseEntryModal .modal-dialog,
        #warehouseEntryModal .modal-content,
        #warehouseEntryLotsModal.show,
        #warehouseEntryLotsModal .modal-dialog,
        #warehouseEntryLotsModal .modal-content {
            pointer-events: auto;
        }

        body.warehouse-entry-active .swal2-container {
            z-index: 3000 !important;
        }

        .warehouse-entry-lots-modal-content {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 18px 45px rgba(25, 39, 52, .22);
        }

        .warehouse-entry-lots-metrics {
            display: grid;
            grid-template-columns: minmax(180px, 2fr) repeat(3, minmax(110px, 1fr));
            gap: 8px;
        }

        .warehouse-entry-lots-metrics > div {
            padding: 9px 11px;
            border: 1px solid #e5ecee;
            border-radius: 8px;
            background: #f8fafb;
        }

        .warehouse-entry-lots-metrics small,
        .warehouse-entry-lots-metrics strong {
            display: block;
        }

        .warehouse-entry-lots-metrics small { color: #7a858d; font-size: 9px; }
        .warehouse-entry-lots-metrics strong { font-size: 13px; }
        .warehouse-entry-lots-table input { min-width: 120px; }

        @media (max-width: 767.98px) {
            .warehouse-entry-lots-metrics { grid-template-columns: 1fr 1fr; }
        }

        .warehouse-entry-total-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .warehouse-entry-side-total {
            font-size: 18px;
            font-weight: 700;
            color: #11867a;
            line-height: 1.2;
        }

        .warehouse-entry-modal {
            border-radius: 12px;
            overflow: hidden;
            color: #2e3440;
        }

        #warehouseEntryModal .modal-dialog {
            width: calc(100vw - 120px);
            max-width: 1700px;
            margin: 12px auto;
        }

        #warehouseEntryModal .modal-content {
            display: flex;
            max-height: 90vh;
            flex-direction: column;
            background: #f5f8f9;
        }

        #warehouseEntryModal .modal-body {
            min-height: 0;
            padding: 10px 12px;
            overflow-x: hidden;
            overflow-y: auto;
            flex: 0 1 auto;
        }
        #warehouseEntryModal .warehouse-entry-modal-footer {
            position: relative;
            z-index: 15;
            flex: 0 0 auto;
            border-top: 1px solid #dde7e9;
            background: rgba(255, 255, 255, .98);
            box-shadow: 0 -6px 18px rgba(32, 56, 65, .07);
        }

        #warehouseEntryModal .warehouse-entry-tabs-column { display: flex; min-width: 0; flex-direction: column; }
        #warehouseEntryModal .warehouse-entry-form-tabs { padding: 6px; border: 1px solid #dce8e8; border-radius: 12px; background: linear-gradient(145deg, #fff, #f7faf9); box-shadow: 0 6px 18px rgba(31, 85, 79, .06); }
        #warehouseEntryModal .warehouse-entry-form-tabs .nav { gap: 6px; overflow-x: auto; scrollbar-width: thin; }
        #warehouseEntryModal .warehouse-entry-form-tabs .nav-link { display: flex; min-width: max-content; gap: 7px; padding: 8px 11px; align-items: center; border: 1px solid #e4ecec; border-radius: 8px; color: #647572; background: #f4f7f7; font-size: 11px; font-weight: 800; white-space: nowrap; }
        #warehouseEntryModal .warehouse-entry-form-tabs .nav-link.active { border-color: #b9dfda; color: #0c756a; background: linear-gradient(135deg, #e8f7f5, #d9f0ed); box-shadow: 0 4px 11px rgba(17, 134, 122, .12); }
        #warehouseEntryModal .warehouse-entry-form-tab-content { padding-top: 8px; }
        #warehouseEntryModal .warehouse-entry-form-tab-content > .tab-pane > .card { margin-bottom: 0; }
        #warehouseEntryOriginalExpensesCard{border:1px solid #e1ecea!important;overflow:hidden}
        #warehouseEntryOriginalExpensesCard .card-header{padding:13px 15px!important;background:linear-gradient(135deg,#f7fbfa,#eef7f5)}
        .warehouse-entry-expense-body{padding:14px!important;background:#f7f9f9}
        .warehouse-entry-delivery-help{display:flex;padding:12px 14px;gap:11px;align-items:flex-start;border:1px solid #cfe5e1;border-left:4px solid #159484;border-radius:10px;background:#f1faf8;color:#385d55}.warehouse-entry-delivery-help>i{margin-top:2px;color:#11897b;font-size:18px}.warehouse-entry-delivery-help strong,.warehouse-entry-delivery-help span{display:block}.warehouse-entry-delivery-help strong{font-size:11px}.warehouse-entry-delivery-help span{margin-top:2px;color:#68817b;font-size:10px}
        @media (max-width:575.98px){.warehouse-entry-expenses-table tbody td:nth-child(1):before{content:'Tipo'!important}.warehouse-entry-expenses-table tbody td:nth-child(2):before{content:'Agencia / Responsable'!important}.warehouse-entry-expenses-table tbody td:nth-child(3):before{content:'Documento'!important}.warehouse-entry-expenses-table tbody td:nth-child(4):before{content:'Importe'!important}.warehouse-entry-expenses-table tbody td:nth-child(5):before{content:'IGV'!important}.warehouse-entry-expenses-table tbody td:nth-child(6):before{content:'Observación'!important}.warehouse-entry-expenses-table tbody td:nth-child(7):before{content:'Documentos'!important}.warehouse-entry-expenses-table tbody td:nth-child(8):before{content:'Acciones'!important}}
        .warehouse-entry-expense-kpis{margin:0 -5px 10px}.warehouse-entry-expense-kpis>div{padding:0 5px;margin-bottom:6px}.warehouse-entry-expense-kpi{display:flex;min-height:68px;padding:11px 12px;gap:10px;align-items:center;border:1px solid #deebe8;border-radius:11px;background:#fff;box-shadow:0 4px 13px rgba(37,62,54,.04)}.warehouse-entry-expense-kpi>i{display:flex;width:34px;height:34px;align-items:center;justify-content:center;flex:0 0 auto;border-radius:9px;background:#e4f5f2;color:#138478}.warehouse-entry-expense-kpi span{color:#74827e;font-size:9px;font-weight:700;text-transform:uppercase}.warehouse-entry-expense-kpi strong{display:block;margin-top:2px;color:#25483f;font-size:15px;letter-spacing:0;text-transform:none}
        .warehouse-entry-expense-form{padding:14px 14px 12px;border:1px solid #e3ecea;border-radius:12px;background:#fff;box-shadow:0 5px 16px rgba(37,62,54,.045)}
        .warehouse-entry-expense-form .row{margin-right:-5px;margin-left:-5px}.warehouse-entry-expense-form .form-group{margin-bottom:10px;padding-right:5px;padding-left:5px}.warehouse-entry-expense-form label{margin-bottom:4px;color:#697773;font-size:9px;font-weight:800;letter-spacing:.035em}
        .warehouse-entry-expense-document-card{height:100%;padding:11px;border:1px solid #dfeae8;border-radius:11px;background:#fbfdfc}.warehouse-entry-expense-document-card.is-invoice{border-top:3px solid #168b78}.warehouse-entry-expense-document-card.is-payment{border-top:3px solid #4d7fa8}.warehouse-entry-expense-document-heading{display:flex;gap:9px;margin-bottom:8px;align-items:center}.warehouse-entry-expense-document-heading>span{display:grid;width:32px;height:32px;place-items:center;flex:0 0 auto;border-radius:9px;background:#e1f3ef;color:#138478}.warehouse-entry-expense-document-card.is-payment .warehouse-entry-expense-document-heading>span{background:#e8f0f7;color:#47789f}.warehouse-entry-expense-document-heading strong,.warehouse-entry-expense-document-heading small{display:block}.warehouse-entry-expense-document-heading strong{color:#31534c;font-size:10px}.warehouse-entry-expense-document-heading small{margin-top:1px;color:#788984;font-size:8px}.warehouse-entry-expense-document-help{display:block;margin-top:7px;color:#788984;font-size:8px;line-height:1.35}
        .warehouse-entry-expense-file-picker{position:relative;min-height:58px;border:1px dashed #bcd7d2;border-radius:10px;background:linear-gradient(145deg,#f9fcfb,#f0f8f6);transition:border-color .18s,box-shadow .18s,transform .18s}
        .warehouse-entry-expense-file-picker:hover{border-color:#54a99e;box-shadow:0 5px 14px rgba(17,134,122,.09);transform:translateY(-1px)}
        .warehouse-entry-expense-file-picker.has-file{border-style:solid;border-color:#a9d8d1;background:#f3fbf9}
        .warehouse-entry-expense-file-input{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;opacity:0!important;pointer-events:none}
        .warehouse-entry-expense-file-empty,.warehouse-entry-expense-file-selected{display:flex;min-height:58px;padding:9px 11px;gap:10px;align-items:center;color:#4c5e59;cursor:pointer}
        .warehouse-entry-expense-file-icon{display:inline-flex;width:36px;height:36px;align-items:center;justify-content:center;flex:0 0 auto;border-radius:10px;background:#dff2ee;color:#11867a;font-size:15px}
        .warehouse-entry-expense-file-empty>span:nth-child(2),.warehouse-entry-expense-file-info{display:block;min-width:0;flex:1}.warehouse-entry-expense-file-empty strong,.warehouse-entry-expense-file-empty small,.warehouse-entry-expense-file-info strong,.warehouse-entry-expense-file-info small{display:block}
        .warehouse-entry-expense-file-empty strong,.warehouse-entry-expense-file-info strong{overflow:hidden;color:#31534c;font-size:11px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.warehouse-entry-expense-file-empty small,.warehouse-entry-expense-file-info small{margin-top:2px;color:#7d8e89;font-size:9px}
        .warehouse-entry-expense-file-arrow{color:#78aaa2;font-size:10px}.warehouse-entry-expense-file-selected{cursor:default}.warehouse-entry-expense-file-selected .btn{padding:4px 8px;font-size:9px;font-weight:700}.warehouse-entry-expense-file-selected .btn-light{border:1px solid #d9e5e2;color:#667873}
        .warehouse-entry-expense-document-links{display:flex;gap:4px;flex-direction:column;align-items:flex-start}.warehouse-entry-expense-document-links span,.warehouse-entry-expense-document-links a{font-size:8px}.warehouse-entry-expense-document-links .text-muted{padding:2px 0}
        .warehouse-entry-expense-action{display:flex;gap:12px;margin-top:2px;padding-top:11px;align-items:center;justify-content:flex-end;border-top:1px solid #edf2f0}.warehouse-entry-expense-action small{margin-right:auto;color:#81908c;font-size:9px}.warehouse-entry-expense-action .btn{min-width:135px;padding:7px 14px;border-radius:8px;font-weight:700;box-shadow:0 5px 12px rgba(23,162,184,.16)}
        .warehouse-entry-expenses-table-wrap{margin-top:12px;border:1px solid #e2ebe9;border-radius:11px;overflow:hidden;background:#fff}.warehouse-entry-expenses-table{width:100%;margin:0;table-layout:fixed}.warehouse-entry-expenses-table thead th{padding:8px 7px;border:0;border-bottom:1px solid #dfe9e7;background:#edf5f3;color:#61716d;font-size:8px;font-weight:900;letter-spacing:.03em;text-transform:uppercase;white-space:normal}.warehouse-entry-expenses-table tbody td{padding:9px 7px;vertical-align:middle;border-color:#edf2f1;color:#44534f;font-size:10px;overflow-wrap:anywhere}.warehouse-entry-expenses-table .badge{padding:4px 7px;border-radius:999px;font-size:8px}
        .warehouse-entry-expense-summary{display:flex;gap:9px;justify-content:flex-end;margin-top:10px}.warehouse-entry-expense-summary span{min-width:170px;padding:9px 12px;border:1px solid #d7eae6;border-radius:9px;background:#eef8f6;color:#5b706a;font-size:10px}.warehouse-entry-expense-summary strong{float:right;color:#08766a;font-size:13px}
        #warehouseEntryModal #warehouse_entry_tab_data .card-body { padding: 12px 14px 6px; }
        #warehouseEntryModal #warehouse_entry_tab_data .row { margin-right: -5px; margin-left: -5px; }
        #warehouseEntryModal #warehouse_entry_tab_data .form-group { margin-bottom: 9px; padding-right: 5px; padding-left: 5px; }

        @media (min-width: 1200px) {
            #warehouseEntryModal #warehouse_entry_tab_data .form-group:not(.col-md-12) {
                width: 25%;
                max-width: 25%;
                flex: 0 0 25%;
            }
        }
        #warehouseEntryModal .warehouse-entry-table-scroll { max-height: 48vh; }

        #warehouseEntryModal .warehouse-entry-side-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; }
        #warehouseEntryModal .warehouse-entry-side-metrics > div { padding: 7px; border: 1px solid #e2eceb; border-radius: 8px; background: #f7fbfa; text-align: center; }
        #warehouseEntryModal .warehouse-entry-side-metrics strong, #warehouseEntryModal .warehouse-entry-side-metrics small { display: block; }
        #warehouseEntryModal .warehouse-entry-side-metrics strong { color: #11867a; font-size: 15px; }
        #warehouseEntryModal .warehouse-entry-side-metrics small { color: #788681; font-size: 9px; text-transform: uppercase; }
        .warehouse-entry-lot-selected-info { display: flex; gap: 6px; padding: 9px 11px; flex-direction: column; border: 1px solid #cfe5e1; border-radius: 8px; background: #f1faf8; font-size: 11px; }
        .warehouse-entry-lot-selected-info strong { color: #25544d; }
        .warehouse-entry-lot-selected-info span { color: #667773; }
        .warehouse-entry-lot-document-group { margin-bottom: 9px; border: 1px solid #e2eaea; border-radius: 9px; overflow: hidden; background: #fff; }
        .warehouse-entry-lot-document-group-title { display: flex; justify-content: space-between; gap: 10px; padding: 8px 11px; background: #f4f8f7; font-size: 11px; }
        .warehouse-entry-lot-document-group-title span { color: #64736f; }
        .warehouse-entry-lot-document-row { display: flex; justify-content: space-between; gap: 10px; padding: 7px 11px; align-items: center; border-top: 1px solid #edf1f1; color: #46534f; font-size: 11px; }
        .warehouse-entry-lot-documents-empty { padding: 18px; border: 1px dashed #d7e1e0; border-radius: 9px; color: #7c8986; background: #fafcfc; text-align: center; font-size: 11px; }
        .warehouse-entry-lot-documents-empty i { display: block; margin-bottom: 5px; font-size: 20px; }

        .vwe-lot-doc-accordion { overflow: hidden; margin-top: 10px; border: 1px solid #dce8e5; border-radius: 12px; background: #fff; box-shadow: 0 4px 12px rgba(37, 90, 75, .045); }
        .vwe-lot-doc-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 11px 13px; background: linear-gradient(110deg, #f3f9f7, #fbfdfc); transition: background-color .18s ease; }
        .vwe-lot-doc-accordion.is-open .vwe-lot-doc-header { background: #edf7f3; }
        .vwe-lot-doc-identity { display: flex; min-width: 0; align-items: center; gap: 10px; text-align: left; }
        .vwe-lot-doc-icon { display: inline-grid; flex: 0 0 35px; width: 35px; height: 35px; place-items: center; border-radius: 9px; background: #dfeee9; color: #28765e; }
        .vwe-lot-doc-identity>div { min-width: 0; }
        .vwe-lot-doc-identity small,
        .vwe-lot-doc-identity strong { display: block; }
        .vwe-lot-doc-identity small { color: #748780; font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .vwe-lot-doc-identity strong { overflow: hidden; max-width: 650px; color: #29473d; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
        .vwe-lot-doc-meta { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
        .vwe-lot-doc-meta span { display: inline-flex; align-items: center; gap: 4px; padding: 3px 7px; border: 1px solid #dce8e4; border-radius: 999px; background: #fff; color: #60736c; font-size: 9px; font-weight: 700; }
        .vwe-lot-doc-meta i { color: #3b876e; font-size: 8px; }
        .vwe-lot-doc-meta .vwe-lot-doc-count { border-color: #cde5dc; background: #eaf6f1; color: #276b55; }
        .vwe-lot-doc-toggle { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 7px; padding: 6px 10px; border: 1px solid #4a9079; border-radius: 8px; background: #fff; color: #2b7059; font-size: 9px; font-weight: 800; cursor: pointer; transition: .18s ease; }
        .vwe-lot-doc-toggle:hover,
        .vwe-lot-doc-toggle:focus { outline: 0; background: #397f68; color: #fff; box-shadow: 0 3px 8px rgba(57, 127, 104, .18); }
        .vwe-lot-doc-body { border-top: 1px solid #dce8e5; background: #fff; }
        .vwe-lot-doc-table { min-width: 760px; }
        .vwe-lot-doc-table th { padding: 8px 10px; border: 0; border-bottom: 1px solid #dce7e3; background: #f7faf9; color: #60736c; font-size: 9px; font-weight: 800; text-transform: uppercase; white-space: nowrap; }
        .vwe-lot-doc-table td { padding: 8px 10px; border-top: 1px solid #edf2f0; color: #495a54; font-size: 10px; vertical-align: middle; }
        .vwe-lot-doc-type { display: inline-block; padding: 3px 7px; border-radius: 999px; background: #edf6f3; color: #326d59; font-weight: 700; white-space: nowrap; }
        .vwe-lot-doc-file { display: inline-block; overflow: hidden; max-width: 230px; color: #50635c; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
        .vwe-lot-doc-actions { display: inline-flex; gap: 5px; white-space: nowrap; }
        .vwe-lot-doc-actions .btn { padding: 4px 7px; font-size: 9px; }
        .vwe-lot-doc-empty { padding: 17px; color: #7b8984; background: #fbfcfc; font-size: 10px; text-align: center; }

        @media (max-width: 767.98px) {
            .vwe-lot-doc-header { align-items: stretch; flex-direction: column; }
            .vwe-lot-doc-identity { align-items: flex-start; }
            .vwe-lot-doc-identity strong { max-width: calc(100vw - 150px); white-space: normal; }
            .vwe-lot-doc-toggle { width: 100%; justify-content: center; }
        }

        #warehouseEntryModal .warehouse-entry-review-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 9px; }
        #warehouseEntryModal .warehouse-entry-review-grid > div { min-height: 58px; padding: 9px 11px; border: 1px solid #e4ecec; border-radius: 9px; background: #fff; }
        #warehouseEntryModal .warehouse-entry-review-grid small, #warehouseEntryModal .warehouse-entry-review-grid strong { display: block; }
        #warehouseEntryModal .warehouse-entry-review-grid small { color: #7b8885; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        #warehouseEntryModal .warehouse-entry-review-grid strong { margin-top: 3px; color: #34423f; font-size: 12px; word-break: break-word; }
        #warehouseEntryModal .warehouse-entry-review-grid .warehouse-entry-review-grand { border-color: #c9e5e1; background: #edf9f7; }
        #warehouseEntryModal .warehouse-entry-review-grand strong { color: #11867a; font-size: 17px; }
        #warehouseEntryModal .warehouse-entry-review-alert, #warehouseEntryModal .warehouse-entry-review-ok { display: flex; gap: 10px; margin-top: 12px; padding: 11px 13px; border-radius: 9px; font-size: 11px; }
        #warehouseEntryModal .warehouse-entry-review-alert { border: 1px solid #f1d89b; color: #725b20; background: #fff9e9; }
        #warehouseEntryModal .warehouse-entry-review-ok { border: 1px solid #bfe2d4; color: #216a50; background: #effaf5; }
        #warehouseEntryModal .warehouse-entry-review-alert ul { margin: 3px 0 0; padding-left: 17px; }
        #warehouseEntryModal .warehouse-entry-review-ok small { display: block; }

        @media (max-width: 991.98px) {
            #warehouseEntryModal .modal-dialog { width: calc(100vw - 20px); max-width: calc(100vw - 20px); margin: 10px auto; }
            #warehouseEntryModal .modal-content { max-height: calc(100vh - 12px); }
            #warehouseEntryModal .warehouse-entry-review-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 575.98px) {
            #warehouseEntryModal .warehouse-entry-review-grid { grid-template-columns: 1fr; }
            #warehouseEntryModal .warehouse-entry-modal-footer .btn { flex: 1; }
            .warehouse-entry-expense-body{padding:8px!important}.warehouse-entry-expense-form{padding:10px}.warehouse-entry-expense-file-selected{flex-wrap:wrap}.warehouse-entry-expense-file-info{flex-basis:calc(100% - 46px)}.warehouse-entry-expense-file-selected .btn{flex:1}.warehouse-entry-expense-action{align-items:stretch;flex-direction:column}.warehouse-entry-expense-action small{margin-right:0}.warehouse-entry-expense-action .btn{width:100%}.warehouse-entry-expense-summary{flex-direction:column}.warehouse-entry-expense-summary span{width:100%;min-width:0}
            .warehouse-entry-expenses-table-wrap{border:0;overflow:visible;background:transparent}.warehouse-entry-expenses-table,.warehouse-entry-expenses-table tbody,.warehouse-entry-expenses-table tr,.warehouse-entry-expenses-table td{display:block;width:100%}.warehouse-entry-expenses-table thead{display:none}.warehouse-entry-expenses-table tbody tr{margin-bottom:9px;padding:8px 10px;border:1px solid #e1ebe9;border-radius:10px;background:#fff;box-shadow:0 3px 10px rgba(37,62,54,.04)}.warehouse-entry-expenses-table tbody td{display:flex;padding:5px 0;justify-content:space-between;border:0;text-align:right!important}.warehouse-entry-expenses-table tbody td:before{margin-right:10px;color:#7b8985;font-size:8px;font-weight:900;text-transform:uppercase}.warehouse-entry-expenses-table tbody td:nth-child(1):before{content:'Tipo'}.warehouse-entry-expenses-table tbody td:nth-child(2):before{content:'Agencia / Responsable'}.warehouse-entry-expenses-table tbody td:nth-child(3):before{content:'Documento'}.warehouse-entry-expenses-table tbody td:nth-child(4):before{content:'Importe'}.warehouse-entry-expenses-table tbody td:nth-child(5):before{content:'IGV'}.warehouse-entry-expenses-table tbody td:nth-child(6):before{content:'Observación'}.warehouse-entry-expenses-table tbody td:nth-child(7):before{content:'Documentos'}.warehouse-entry-expenses-table tbody td:nth-child(8):before{content:'Acciones'}
        }

        .warehouse-entry-modal .modal-header {
            padding: 12px 16px;
        }

        .warehouse-entry-modal-header {
            background: linear-gradient(135deg, #11867a, #159f93);
            border-bottom: 0;
        }

        .warehouse-entry-modal-header .modal-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
            letter-spacing: 0;
        }

        .warehouse-entry-modal-header small {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, .78);
            font-size: 11px;
            font-weight: 400;
        }

        .warehouse-entry-modal-header .close {
            font-size: 20px;
            padding: 12px 16px;
            text-shadow: none;
            opacity: .85;
        }

        .warehouse-entry-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warehouse-entry-header-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .16);
            color: #fff;
            font-size: 14px;
            flex: 0 0 auto;
        }

        .warehouse-entry-modal-body {
            background: #f4f7f8;
            padding: 14px;
        }

        .warehouse-entry-modal .card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(17, 134, 122, .06) !important;
        }

        .warehouse-entry-card .card-body {
            padding: 12px 14px 8px;
        }

        .warehouse-entry-section-header {
            background: #f8fafb;
            border-bottom: 1px solid #edf1f2 !important;
            padding: 8px 12px !important;
        }

        .warehouse-entry-section-header h6 {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .warehouse-entry-section-header small {
            font-size: 11px;
        }

        .warehouse-entry-modal label {
            margin-bottom: 3px;
            color: #68717a;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .warehouse-entry-modal .form-group {
            margin-bottom: 8px;
        }

        .warehouse-entry-modal .form-control,
        .warehouse-entry-modal .custom-select {
            min-height: 30px;
            height: 30px;
            border-color: #dfe6e8;
            border-radius: 6px;
            font-size: 12px;
            padding: 4px 8px;
        }

        .warehouse-entry-modal textarea.form-control {
            height: auto;
            min-height: 46px;
        }

        .warehouse-entry-modal .select2-container--default .select2-selection--single {
            height: 30px;
            border-color: #dfe6e8;
            border-radius: 6px;
        }

        .warehouse-entry-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 8px;
            padding-right: 24px;
            color: #2e3440;
            font-size: 12px;
            line-height: 28px;
        }

        .warehouse-entry-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 28px;
        }

        .warehouse-entry-side-card .card-body {
            padding: 15px 13px;
        }

        .warehouse-entry-side-card h5 {
            font-size: 13px;
            font-weight: 700;
        }

        .warehouse-entry-side-card small {
            font-size: 10.5px;
        }

        .warehouse-entry-side-card .font-weight-600,
        .warehouse-entry-side-card strong,
        .warehouse-entry-side-card .text-left div {
            font-size: 12px;
        }

        .warehouse-entry-side-card .badge {
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 9px !important;
        }

        .warehouse-entry-side-card hr {
            margin: 10px 0;
        }

        .warehouse-entry-modal .btn {
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 10px;
        }

        .warehouse-entry-modal .btn-sm {
            font-size: 11.5px;
            padding: 4px 9px;
        }

        .warehouse-entry-items-table {
            font-size: 11.5px;
        }

        .warehouse-entry-items-table thead th,
        .warehouse-entry-detail-table thead th {
            border-bottom: 1px solid #e9eef0 !important;
            color: #59636d;
            font-size: 10.5px;
            font-weight: 700;
            padding: 7px 6px;
        }

        .warehouse-entry-items-table tbody td,
        .warehouse-entry-detail-table tbody td {
            padding: 5px 6px;
            vertical-align: middle !important;
        }

        .warehouse-entry-items-table input,
        .warehouse-entry-items-table select {
            min-width: 104px;
            height: 28px;
            font-size: 11.5px;
            padding: 3px 6px;
        }

        .warehouse-entry-items-table .item-article-picker {
            min-width: 220px;
        }

        .warehouse-entry-items-table .item-note {
            min-width: 150px;
        }

        .warehouse-entry-items-table .item-line-total {
            color: #11867a;
            font-size: 12px;
        }

        .warehouse-entry-modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 3;
            background: #fff;
            border-top: 1px solid #e8eef0;
            padding: 10px 14px;
        }

        .warehouse-entry-documents-card {
            border: 1px solid #e6f0f1 !important;
        }

        .warehouse-entry-document-title {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .warehouse-entry-document-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e6f6f4;
            color: #11867a;
            flex: 0 0 auto;
        }

        .warehouse-entry-document-counter {
            padding: 5px 10px;
            border-radius: 999px;
            background: #eef8f7;
            color: #11867a;
            font-size: 11px;
            font-weight: 700;
        }

        .warehouse-entry-document-form {
            padding: 12px;
            border: 1px solid #edf1f2;
            border-radius: 10px;
            background: #fbfdfd;
        }

        .warehouse-entry-document-file .custom-file-label {
            height: 30px;
            padding: 4px 75px 4px 8px;
            border-color: #dfe6e8;
            border-radius: 6px;
            color: #7b8790;
            font-size: 11.5px;
            line-height: 20px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .warehouse-entry-document-file .custom-file-label::after {
            height: 28px;
            padding: 4px 10px;
            color: #11867a;
            background: #e6f6f4;
            border-left-color: #dfe6e8;
            font-size: 11px;
            font-weight: 700;
            line-height: 20px;
            content: "Buscar";
        }

        .warehouse-entry-document-add {
            height: 30px;
            border: 0;
            background: linear-gradient(135deg, #11867a, #159f93);
            box-shadow: 0 8px 18px rgba(17, 134, 122, .16);
        }

        .warehouse-entry-document-add:hover {
            background: linear-gradient(135deg, #0f766e, #11867a);
        }

        .warehouse-entry-documents-table-wrap {
            margin-top: 12px;
            border: 1px solid #edf1f2;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .warehouse-entry-documents-table {
            font-size: 11.5px;
        }

        .warehouse-entry-documents-table thead th {
            padding: 8px 7px;
            border-bottom: 1px solid #e9eef0 !important;
            background: #f8fafb;
            color: #59636d;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .15px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .warehouse-entry-documents-table tbody td {
            padding: 7px;
            border-top: 1px solid #f0f3f4;
            color: #3d4650;
            vertical-align: middle !important;
        }

        .warehouse-entry-document-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            white-space: nowrap;
        }

        .warehouse-entry-document-badge.badge-doc-green {
            background: #e8f8ef;
            color: #16733c;
        }

        .warehouse-entry-document-badge.badge-doc-blue {
            background: #e7f3ff;
            color: #1f6fb2;
        }

        .warehouse-entry-document-badge.badge-doc-teal {
            background: #e6f6f4;
            color: #11867a;
        }

        .warehouse-entry-document-badge.badge-doc-yellow {
            background: #fff7df;
            color: #9a6a00;
        }

        .warehouse-entry-document-badge.badge-doc-gray {
            background: #eef1f4;
            color: #59636d;
        }

        .warehouse-entry-document-file-name {
            display: block;
            max-width: 230px;
            color: #26323b;
            font-weight: 700;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .warehouse-entry-document-actions {
            display: inline-flex;
            justify-content: center;
            gap: 5px;
        }

        .warehouse-entry-document-actions .btn {
            width: 28px;
            height: 28px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        #warehouseEntryModal .warehouse-entry-card > .p-3 {
            padding: 12px 14px !important;
            background: #fff !important;
        }

        #warehouseEntryModal .warehouse-entry-card > .p-3 .col-lg-5 {
            padding: 10px 12px;
            border: 1px solid #d8eeea;
            border-radius: 9px;
            background: #f9fdfc;
        }

        #warehouseEntryModal .warehouse-entry-total-line input {
            max-width: 145px;
            border-color: #d8eeea;
            background: #fff;
            font-weight: 700;
        }

        #warehouseEntryModal .warehouse-entry-total-line.font-weight-bold {
            margin-top: 7px;
            padding-top: 8px;
            border-top: 1px solid #d8eeea;
            color: #11867a;
        }

        #warehouseEntryModal .warehouse-entry-total-line.font-weight-bold input {
            color: #11867a;
            font-size: 13px;
        }

        .warehouse-entry-view-modal .card-body {
            font-size: 12px;
        }

        .warehouse-entry-view-modal .modal-body {
            padding: 16px;
        }

        .warehouse-entry-summary-card {
            padding: 16px 14px;
        }

        .warehouse-entry-summary-label {
            display: block;
            color: #7b8790;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .warehouse-entry-summary-code {
            margin: 3px 0 8px;
            color: #27313a;
            font-size: 20px;
            font-weight: 800;
            line-height: 1.15;
        }

        .warehouse-entry-status-badge {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .25px;
            padding: 5px 10px !important;
        }

        .warehouse-entry-summary-separator {
            margin: 13px 0;
            border-top-color: #edf1f2;
        }

        .warehouse-entry-summary-list {
            display: grid;
            gap: 9px;
        }

        .warehouse-entry-summary-item {
            padding-bottom: 8px;
            border-bottom: 1px solid #edf1f2;
        }

        .warehouse-entry-summary-item small,
        .warehouse-entry-summary-total small {
            display: block;
            color: #7b8790;
            font-size: 10.5px;
            font-weight: 700;
            line-height: 1.2;
        }

        .warehouse-entry-summary-item strong {
            display: block;
            margin-top: 2px;
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
        }

        .warehouse-entry-summary-total {
            margin-top: 3px;
            padding: 10px 12px;
            border: 1px solid #d8eeea;
            border-radius: 8px;
            background: #f3fbfa;
        }

        .warehouse-entry-summary-total div {
            margin-top: 2px;
            color: #11867a;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.15;
        }

        .warehouse-entry-detail-grid {
            margin-left: -5px;
            margin-right: -5px;
        }

        .warehouse-entry-detail-grid > [class*="col-"] {
            padding-left: 5px;
            padding-right: 5px;
            margin-bottom: 10px;
        }

        .warehouse-entry-detail-field {
            min-height: 54px;
            padding: 8px 10px;
            border: 1px solid #edf1f2;
            border-radius: 8px;
            background: #fff;
        }

        .warehouse-entry-detail-field-wide {
            min-height: 48px;
        }

        .warehouse-entry-detail-field small {
            display: block;
            margin-bottom: 3px;
            color: #7b8790;
            font-size: 10.5px;
            font-weight: 700;
            line-height: 1.15;
        }

        .warehouse-entry-detail-field strong {
            display: block;
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
            word-break: break-word;
        }

        .warehouse-entry-detail-table-wrap {
            border-top: 1px solid #edf1f2;
        }

        .warehouse-entry-detail-table {
            font-size: 11.5px;
        }

        .warehouse-entry-detail-table thead th {
            background: #f8fafb;
            text-transform: uppercase;
            letter-spacing: .15px;
        }

        .warehouse-entry-detail-table tbody td {
            border-top: 1px solid #f0f3f4;
            color: #3d4650;
            font-size: 11.5px;
        }

        .warehouse-entry-detail-table tbody td:nth-child(2) {
            min-width: 220px;
            color: #26323b;
            font-weight: 700;
            text-align: left;
        }

        .warehouse-entry-detail-footer {
            background: #fff;
            border-top: 1px solid #edf1f2;
            padding: 12px 14px;
        }

        .warehouse-entry-totals-card {
            padding: 10px 12px;
            border: 1px solid #d8eeea;
            border-radius: 9px;
            background: #f9fdfc;
        }

        .warehouse-entry-total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            color: #5e6973;
            font-size: 12px;
            line-height: 1.5;
        }

        .warehouse-entry-total-row strong {
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
        }

        .warehouse-entry-total-row-grand {
            margin-top: 6px;
            padding-top: 8px;
            border-top: 1px solid #d8eeea;
            color: #11867a;
            font-weight: 800;
        }

        .warehouse-entry-total-row-grand strong,
        .warehouse-entry-total-row-grand span {
            color: #11867a;
            font-size: 18px;
            font-weight: 800;
        }

        .warehouse-entry-view-modal h3 {
            font-size: 20px;
            line-height: 1.15;
        }

        .warehouse-entry-view-modal .font-weight-bold {
            font-size: 12px;
        }

        .warehouse-entry-view-modal small {
            font-size: 10.5px;
        }

        .warehouse-entry-view-modal .card-footer {
            font-size: 12px;
            padding: 10px 14px;
        }

        .warehouse-entry-view-modal .h5 {
            font-size: 15px;
        }

        .warehouse-entry-view-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 12px;
            padding: 12px 14px;
            border: 1px solid #e5ecee;
            border-radius: 11px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(32, 49, 58, .05);
        }

        .warehouse-entry-view-identity,
        .warehouse-entry-view-facts {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .warehouse-entry-view-identity h3 {
            margin: 1px 0 0;
            color: #27313a;
            font-weight: 800;
        }

        .warehouse-entry-view-identity small,
        .warehouse-entry-view-facts small {
            display: block;
            color: #7b8790;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .warehouse-entry-view-facts > div {
            min-width: 105px;
            padding-left: 12px;
            border-left: 1px solid #edf1f2;
        }

        .warehouse-entry-view-facts strong {
            display: block;
            max-width: 150px;
            color: #34404a;
            font-size: 11.5px;
            line-height: 1.25;
        }

        .warehouse-entry-view-facts .warehouse-entry-view-total strong {
            color: #11867a;
            font-size: 16px;
        }

        .warehouse-entry-tabs-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            margin-bottom: 12px;
            scrollbar-width: thin;
        }

        .warehouse-entry-view-tabs {
            flex-wrap: nowrap;
            min-width: max-content;
            padding: 4px;
            border: 1px solid #e5ecee;
            border-radius: 10px;
            background: #f5f8f9;
        }

        .warehouse-entry-view-tabs .nav-link {
            margin-right: 3px;
            padding: 7px 13px;
            border-radius: 7px;
            color: #66727b;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .warehouse-entry-view-tabs .nav-link.active {
            color: #fff;
            background: #11867a;
            box-shadow: 0 2px 7px rgba(17, 134, 122, .2);
        }

        .warehouse-entry-view-tab-content {
            min-height: 280px;
        }

        .warehouse-entry-detail-lot-row td {
            background: #fbfdfd;
        }

        .warehouse-entry-detail-lot-row:hover td {
            background: #f3faf8 !important;
        }

        .warehouse-entry-empty-state i {
            color: #a6b3b8;
            font-size: 22px;
        }

        @media (max-width: 991.98px) {
            .warehouse-entry-view-heading,
            .warehouse-entry-view-facts {
                align-items: stretch;
                flex-direction: column;
            }

            .warehouse-entry-view-facts {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
                gap: 8px;
            }

            .warehouse-entry-view-facts > div {
                padding: 7px 9px;
                border: 1px solid #edf1f2;
                border-radius: 7px;
            }

            .warehouse-entry-view-facts strong {
                max-width: none;
            }
        }

        @media (max-width: 575.98px) {
            .warehouse-entry-view-modal .modal-body { padding: 10px; }
            .warehouse-entry-view-identity { flex-wrap: wrap; }
            .warehouse-entry-view-facts { grid-template-columns: 1fr; }
            .warehouse-entry-view-modal .warehouse-entry-detail-table-wrap,
            .warehouse-entry-view-modal .warehouse-entry-documents-table-wrap { max-width: 100%; overflow-x: auto; }
        }

        @media (min-width: 1200px) {
            #warehouseEntryModal .modal-xl {
                width: calc(100vw - 120px);
                max-width: 1700px;
            }

            #warehouseEntryViewModal .modal-xl {
                max-width: 1180px;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            warehouseEntryList: "{{ route('admin.warehouse-entries.list') }}",
            warehouseEntryStore: "{{ route('admin.warehouse-entries.store') }}",
            warehouseEntryUpdate: "{{ url('admin/warehouse-entries') }}",
            warehouseEntryDelete: "{{ url('admin/warehouse-entries') }}",
            warehouseEntryShow: "{{ url('admin/warehouse-entries') }}",
            warehouseEntryGenerateNumber: "{{ route('admin.warehouse-entries.generateNumber') }}",
            warehouseEntryLoadSupplierOrderItems: "{{ route('admin.warehouse-entries.loadSupplierOrderItems') }}",
            supplierPurchaseOrderLogisticsStatus: "{{ url('admin/supplier-purchase-orders') }}"
        });
    </script>

    @vite(['resources/js/pages/warehouse-entry.js'])
@endpush

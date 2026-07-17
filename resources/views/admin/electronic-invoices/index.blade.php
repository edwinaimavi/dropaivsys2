@extends('layouts.app')

@section('subtitle', 'Facturacion Electronica')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-file-invoice-dollar text-success"></i>
                    Facturaci&oacute;n Electr&oacute;nica
                </h1>
                <small class="text-muted">Comprobantes listos para futura integraci&oacute;n con APIs Per&uacute;</small>
            </div>
            @can('admin.electronic-invoices.store')
            <button id="btnCreateElectronicInvoice" class="btn btn-success shadow-sm px-4" type="button">
                <i class="fas fa-plus-circle mr-1"></i>
                Nuevo Comprobante
            </button>
            @endcan
        </div>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-success"></i>
                Lista de comprobantes
            </h5>
            <small class="text-muted">PDF local, payload preparado y estados SUNAT pendientes de integraci&oacute;n</small>
        </div>
        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableElectronicInvoice" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>TIPO</th>
                            <th>N&Uacute;MERO</th>
                            <th>CLIENTE</th>
                            <th>DOCUMENTO</th>
                            <th>MONEDA</th>
                            <th>TOTAL</th>
                            <th>ESTADO SUNAT</th>
                            <th>ESTADO</th>
                            <th>F. EMISI&Oacute;N</th>
                            <th width="210">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.electronic-invoices.partials.modal')
    @include('admin.electronic-invoices.partials.viewModal')
@stop

@push('css')
    <style>
        .rounded-xl { border-radius: 18px; }
        #tableElectronicInvoice thead th {
            border: 0 !important;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        #tableElectronicInvoice tbody td {
            font-size: 12px;
            vertical-align: middle !important;
        }
        .invoice-modal-dialog {
            width: 95vw;
            max-width: 95vw;
        }
        .electronic-invoice-modal {
            max-height: 90vh;
            overflow: hidden;
            border-radius: 14px;
        }
        .invoice-modal-header,
        .electronic-invoice-view-header {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            padding: 12px 16px;
        }
        .invoice-header-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .16);
            font-size: 20px;
        }
        .invoice-modal-body {
            max-height: calc(90vh - 122px);
            overflow-y: auto;
            padding: 12px;
            background: #f5f8fb;
        }
        .invoice-shell {
            display: grid;
            grid-template-columns: 245px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
        }
        .invoice-summary-card,
        .invoice-section,
        .invoice-total-card,
        .electronic-invoice-card {
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #fff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .045);
        }
        .invoice-summary-card {
            position: sticky;
            top: 0;
            padding: 14px;
            text-align: center;
        }
        .invoice-summary-icon {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #fff;
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            font-size: 26px;
        }
        .invoice-summary-block {
            padding: 8px 0;
            border-top: 1px solid #eef2f7;
            text-align: left;
        }
        .invoice-summary-block small,
        .invoice-summary-total small {
            display: block;
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .invoice-summary-block strong {
            display: block;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.25;
            word-break: break-word;
        }
        .invoice-summary-total {
            margin-top: 10px;
            padding: 10px;
            border-radius: 9px;
            color: #fff;
            background: #0f766e;
            text-align: left;
        }
        .invoice-summary-total small,
        .invoice-summary-total strong {
            color: #fff;
        }
        .invoice-summary-total strong {
            font-size: 22px;
            line-height: 1.1;
        }
        .invoice-main-panel {
            min-width: 0;
        }
        .invoice-section {
            padding: 10px;
            margin-bottom: 10px;
        }
        .invoice-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            color: #0f766e;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .invoice-section-header i {
            margin-right: 4px;
        }
        .compact-row {
            margin-left: -5px;
            margin-right: -5px;
        }
        .compact-row > [class*="col-"] {
            padding-left: 5px;
            padding-right: 5px;
        }
        .electronic-invoice-modal .form-group {
            margin-bottom: 7px;
        }
        .electronic-invoice-modal label {
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .invoice-compact-input,
        .electronic-invoice-modal .form-control-sm,
        .electronic-invoice-modal .select2-container--default .select2-selection--single {
            min-height: 31px;
            border-color: #dbe4ee;
            border-radius: 7px;
            font-size: 12px;
        }
        .electronic-invoice-modal textarea.form-control-sm {
            min-height: 31px;
            resize: vertical;
        }
        .electronic-invoice-card-title {
            color: #0f766e;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .invoice-items-scroll {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .electronic-invoice-items-table {
            min-width: 1480px;
        }
        .electronic-invoice-items-table th {
            background: #f8fafc;
            color: #475569;
            border-top: 0;
            font-size: 10px;
            font-weight: 900;
            padding: 6px 5px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .electronic-invoice-items-table td {
            vertical-align: middle !important;
            padding: 5px;
            white-space: nowrap;
        }
        .electronic-invoice-items-table input,
        .electronic-invoice-items-table select,
        .electronic-invoice-items-table .select2-container {
            min-width: 92px;
            font-size: 11px;
        }
        .electronic-invoice-items-table .invoice-article-cell,
        .electronic-invoice-items-table .item-article,
        .electronic-invoice-items-table .item-article + .select2-container {
            min-width: 210px;
        }
        .electronic-invoice-items-table .item-description {
            min-width: 210px;
        }
        .invoice-bottom-grid {
            display: grid;
            grid-template-columns: 270px minmax(220px, 1fr) minmax(260px, 1fr);
            gap: 10px;
            align-items: start;
        }
        .invoice-total-card {
            padding: 10px;
        }
        .invoice-total-line,
        .electronic-invoice-total-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 3px 0;
            font-size: 12px;
        }
        .invoice-total-line strong,
        .electronic-invoice-total-line strong {
            color: #0f766e;
        }
        .invoice-grand-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 6px;
            padding: 8px 10px;
            border-radius: 8px;
            color: #fff;
            background: #0f766e;
            font-weight: 900;
        }
        .invoice-grand-total strong {
            color: #fff;
            font-size: 19px;
        }
        .electronic-invoice-view-grid small {
            display: block;
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .electronic-invoice-view-grid strong {
            display: block;
            color: #1e293b;
            margin-bottom: 10px;
        }
        .sticky-modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 3;
            padding: 10px 14px;
            background: #fff;
            border-top: 1px solid #e2e8f0 !important;
            box-shadow: 0 -8px 18px rgba(15, 23, 42, .06);
        }
        .invoice-add-btn {
            padding: 3px 10px;
            font-size: 11px;
        }
        @media (max-width: 991.98px) {
            .invoice-modal-dialog {
                width: 98vw;
                max-width: 98vw;
                margin: .5rem auto;
            }
            .invoice-shell {
                grid-template-columns: 1fr;
            }
            .invoice-summary-card {
                position: static;
                display: grid;
                grid-template-columns: 72px repeat(2, minmax(0, 1fr));
                gap: 8px;
                text-align: left;
            }
            .invoice-summary-icon {
                margin: 0;
            }
            .invoice-summary-total {
                grid-column: 1 / -1;
            }
            .invoice-bottom-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.electronicInvoiceData = {
            companies: @json($companies),
            customers: @json($customers),
            currencies: @json($currencies),
            series: @json($series),
            articles: @json($articles),
            quotes: @json($quotes),
            customerPurchaseOrders: @json($customerPurchaseOrders),
            warehouseEntries: @json($warehouseEntries),
            taxAffectations: @json($taxAffectations),
        };
        window.routes = Object.assign(window.routes || {}, {
            electronicInvoiceList: "{{ route('admin.electronic-invoices.list') }}",
            electronicInvoiceStore: "{{ route('admin.electronic-invoices.store') }}",
            electronicInvoiceShow: "{{ url('admin/electronic-invoices') }}",
            electronicInvoiceUpdate: "{{ url('admin/electronic-invoices') }}",
            electronicInvoiceDelete: "{{ url('admin/electronic-invoices') }}",
            electronicInvoicePayload: "{{ url('admin/electronic-invoices') }}",
            electronicInvoiceSend: "{{ url('admin/electronic-invoices') }}",
            electronicInvoiceSeriesNextNumber: "{{ route('admin.electronic-invoice-series.nextNumber') }}"
        });
    </script>
    @vite('resources/js/pages/electronic-invoice.js')
@endpush

@extends('layouts.app')

@section('subtitle', 'Órdenes de Compra de Clientes')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-clipboard-check text-primary"></i>
                    Órdenes de Compra de Clientes
                </h1>
                <small class="text-muted">
                    Gestión de pedidos adjudicados desde cotizaciones o registrados directamente.
                </small>
            </div>

            @can('admin.customer-purchase-orders.store')
            <button id="btnCreateCustomerPurchaseOrder" class="btn btn-primary shadow-sm px-4" type="button">
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
                <li class="breadcrumb-item active">Órdenes de Compra de Clientes</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="mb-2">
                    <h5 class="mb-1 font-weight-bold text-dark">
                        <i class="fas fa-list text-primary"></i>
                        Lista de Órdenes de Compra
                    </h5>
                    <small class="text-muted">Pedidos pendientes ordenados por urgencia de vencimiento</small>
                </div>

                <button id="btnToggleSuppliedOrders" type="button"
                    class="btn btn-outline-secondary btn-sm mb-2"
                    aria-pressed="false">
                    <i class="fas fa-eye mr-1"></i>
                    Mostrar atendidas/finalizadas
                </button>
            </div>
        </div>

        <div class="card-body pt-2">
            <div class="customer-order-status-filters mb-3" role="group" aria-label="Filtrar órdenes por estado">
                <button type="button" class="customer-order-filter is-active" data-status-filter="active">Activas</button>
                <button type="button" class="customer-order-filter" data-status-filter="registered" title="Orden registrada, pendiente de compra al proveedor.">Registradas</button>
                <button type="button" class="customer-order-filter" data-status-filter="in_purchase" title="Ya existe una compra a proveedor vinculada, pero la mercadería aún no ha ingresado completa al almacén.">Compra en proceso</button>
                <button type="button" class="customer-order-filter" data-status-filter="partial_entered" title="Llegó parte de la mercadería al almacén.">Ingreso parcial</button>
                <button type="button" class="customer-order-filter" data-status-filter="entered" title="Mercadería ingresada, falta atención o despacho.">Abastecidas en almacén</button>
                <button type="button" class="customer-order-filter" data-status-filter="partial_dispatched" title="Parte de la mercadería ya salió; aún queda saldo pendiente.">Despacho parcial</button>
                <button type="button" class="customer-order-filter" data-status-filter="attended" title="Mercadería despachada o atención cerrada.">Atendidas / Despachadas</button>
                <button type="button" class="customer-order-filter" data-status-filter="overdue">Vencidas</button>
                <button type="button" class="customer-order-filter" data-status-filter="all">Todas</button>
            </div>
            <div class="table-responsive">
                <table id="tableCustomerPurchaseOrder" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>CÓDIGO</th>
                            <th>NRO ORDEN COMPRA</th>
                            <th>CLIENTE</th>
                            <th>GESTOR</th>
                            <th>EMPRESA</th>
                            <th>MONEDA</th>
                            <th>TOTAL</th>
                            <th>PLAZO ENTREGA</th>
                            <th>OPERACIÓN</th>
                            <th>ESTADO</th>
                            <th>FACTURACI&Oacute;N / COBRO</th>
                            <th>F. REGISTRO</th>
                            <th width="150">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.customer-purchase-orders.partials.modal')
    @include('admin.customer-purchase-orders.partials.viewModal')
    @include('admin.customer-purchase-orders.partials.closeAttentionModal')
    @include('admin.customer-purchase-orders.partials.dispatchModal')
    @include('admin.customer-returns.partials.modal')
    @can('admin.customer-purchase-orders.invoice')
        @include('admin.electronic-invoices.partials.modal')
    @endcan
@stop

@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableCustomerPurchaseOrder thead th {
            padding: 14px 10px;
            border: 0 !important;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableCustomerPurchaseOrder tbody td {
            padding: 12px 8px;
            border-top: 1px solid #f1f1f1;
            font-size: 13px;
            vertical-align: middle !important;
        }

        #tableCustomerPurchaseOrder tbody tr:hover {
            background: #fafafa;
        }

        #tableCustomerPurchaseOrder .customer-order-code-cell {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            max-width: 100%;
            padding: 5px 7px 5px 9px;
            border: 1px solid rgba(14, 165, 233, .15);
            border-radius: 10px;
            background: rgba(14, 165, 233, .08);
            color: #075985;
            font-size: 12px;
            font-weight: 700;
            transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
        }

        #tableCustomerPurchaseOrder .customer-order-code-cell:hover {
            border-color: rgba(14, 165, 233, .28);
            background: rgba(14, 165, 233, .12);
            box-shadow: 0 5px 14px rgba(3, 105, 161, .08);
        }

        #tableCustomerPurchaseOrder .customer-order-code-icon {
            flex: 0 0 auto;
            color: #ef4444;
        }

        #tableCustomerPurchaseOrder .customer-order-code-text {
            min-width: 0;
            overflow-wrap: anywhere;
            cursor: text;
            line-height: 1.25;
            user-select: text;
            -webkit-user-select: text;
        }

        #tableCustomerPurchaseOrder .customer-order-code-actions {
            display: inline-flex;
            flex: 0 0 auto;
            gap: 3px;
        }

        #tableCustomerPurchaseOrder .customer-order-copy-btn,
        #tableCustomerPurchaseOrder .customer-order-open-btn {
            display: inline-flex;
            width: 25px;
            height: 25px;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            border-radius: 7px;
            background: rgba(255, 255, 255, .82);
            color: #0369a1;
            font-size: 10px;
            text-decoration: none;
            transition: transform .15s ease, background-color .15s ease, color .15s ease;
        }

        #tableCustomerPurchaseOrder .customer-order-copy-btn:hover,
        #tableCustomerPurchaseOrder .customer-order-copy-btn:focus,
        #tableCustomerPurchaseOrder .customer-order-open-btn:hover,
        #tableCustomerPurchaseOrder .customer-order-open-btn:focus {
            background: #fff;
            color: #075985;
            outline: 0;
            text-decoration: none;
            transform: translateY(-1px);
        }

        #tableCustomerPurchaseOrder .customer-order-code-cell.is-copied .customer-order-copy-btn {
            background: #dcfce7;
            color: #15803d;
        }

        #tableCustomerPurchaseOrder .customer-order-copy-feedback {
            position: absolute;
            right: 5px;
            bottom: -19px;
            z-index: 2;
            padding: 2px 6px;
            border-radius: 6px;
            background: #166534;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-2px);
            transition: opacity .15s ease, transform .15s ease;
        }

        #tableCustomerPurchaseOrder .customer-order-code-cell.is-copied .customer-order-copy-feedback {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 575.98px) {
            #tableCustomerPurchaseOrder .customer-order-code-cell {
                gap: 5px;
                padding-left: 7px;
            }
        }

        #tableCustomerPurchaseOrder .customer-cell {
            display: flex;
            min-width: 190px;
            max-width: 290px;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            text-align: left;
        }

        #tableCustomerPurchaseOrder .customer-name-main {
            color: #1f2937;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.2;
        }

        #tableCustomerPurchaseOrder .customer-branch-badge {
            display: inline-flex;
            max-width: 100%;
            align-items: flex-start;
            gap: 6px;
            margin-top: 6px;
            padding: 5px 10px;
            border: 1px solid rgba(16, 185, 129, .20);
            border-radius: 999px;
            background: rgba(16, 185, 129, .12);
            color: #0f766e;
            font-size: 11.5px;
            font-weight: 750;
            line-height: 1.25;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        #tableCustomerPurchaseOrder .customer-branch-badge i {
            margin-top: 1px;
            color: #0f766e;
            font-size: 11px;
            line-height: 1.25;
            opacity: .95;
        }

        @media (max-width: 991.98px) {
            #tableCustomerPurchaseOrder .customer-cell {
                min-width: 170px;
                max-width: 240px;
            }
        }

        .delivery-period-card {
            min-width: 165px;
            padding: 7px 9px;
            border-left: 4px solid;
            border-radius: 10px;
            font-size: 10.5px;
            line-height: 1.35;
            text-align: left;
        }

        .delivery-period-card strong {
            color: #374151;
            font-weight: 800;
        }

        .delivery-period-days {
            margin-top: 2px;
            color: #4b5563;
            font-weight: 700;
        }

        .delivery-period-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 3px 7px;
            border-radius: 999px;
            font-weight: 800;
        }

        .delivery-period-success { border-color: #22c55e; background: #f0fdf4; }
        .delivery-period-success .delivery-period-badge { color: #166534; background: #dcfce7; }
        .delivery-period-warning { border-color: #f59e0b; background: #fffbeb; }
        .delivery-period-warning .delivery-period-badge { color: #92400e; background: #fef3c7; }
        .delivery-period-danger { border-color: #ef4444; background: #fef2f2; }
        .delivery-period-danger .delivery-period-badge { color: #991b1b; background: #fee2e2; }
        .delivery-period-info { border-color: #38bdf8; background: #f0f9ff; }
        .delivery-period-info .delivery-period-badge { color: #075985; background: #e0f2fe; }
        .delivery-period-muted { border-color: #9ca3af; background: #f8fafc; text-align: center; }
        .delivery-period-muted .delivery-period-badge { color: #4b5563; background: #e5e7eb; }
        .delivery-period-completed { border-color:#86b99f;background:#f3f8f5 }
        .delivery-period-completed .delivery-period-badge { color:#35634d;background:#dfeee5 }
        .delivery-period-note{display:block;margin-top:4px;color:#718078;font-size:10px!important;font-weight:600;line-height:1.3}
        .customer-order-status-filters{display:flex;gap:7px;overflow-x:auto;padding:3px 1px 7px;scrollbar-width:thin}
        .customer-order-filter{flex:0 0 auto;padding:6px 11px;border:1px solid #dfe7e4;border-radius:999px;background:#fff;color:#61716b;font-size:10.5px;font-weight:750;transition:.18s}
        .customer-order-filter:hover{border-color:#9fcdbb;background:#f1faf6;color:#176348}
        .customer-order-filter.is-active{border-color:#16845f;background:#16845f;color:#fff;box-shadow:0 7px 16px rgba(22,132,95,.18)}
        .customer-order-filter:focus{outline:0;box-shadow:0 0 0 3px rgba(22,132,95,.16)}
        @media(max-width:575px){.customer-order-status-filters{margin-left:-4px;margin-right:-4px}.customer-order-filter{padding:5px 9px;font-size:10px}}

        .breadcrumb {
            margin-bottom: 0;
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = {
            customerPurchaseOrderList: "{{ route('admin.customer-purchase-orders.list') }}",
            customerPurchaseOrderStore: "{{ route('admin.customer-purchase-orders.store') }}",
            customerPurchaseOrderUpdate: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderDelete: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderShow: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderGenerateCode: "{{ route('admin.customer-purchase-orders.generateCode') }}",
            customerPurchaseOrderCheckNumber: "{{ route('admin.customer-purchase-orders.checkPurchaseOrderNumber') }}",
            customerPurchaseOrderQuoteItems: "{{ url('admin/customer-purchase-orders/quote/:id/items') }}",
            customerPurchaseOrderCustomerBranches: "{{ url('admin/customer-purchase-orders/customer/:id/branches') }}",
            customerPurchaseOrderCustomersSearch: "{{ route('admin.customer-purchase-orders.customers.search') }}",
            customerPurchaseOrderCustomersQuickStore: "{{ route('admin.customer-purchase-orders.customers.quick-store') }}",
            customerPurchaseOrderSellerUser: "{{ url('admin/customer-purchase-orders/seller-user') }}",
            customerPurchaseOrderCloseAttention: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderDispatchData: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderDispatchStore: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderDispatchReverse: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderDispatchDocuments: "{{ url('admin/customer-purchase-orders') }}",
            customerPurchaseOrderCustomerDocumentConsult: "{{ url('admin/document-lookup/TYPE_PLACEHOLDER/DOC_PLACEHOLDER') }}",
            quickStoreArticle: "{{ route('admin.articles.quick-store') }}",
            sunatExistenceTypes: "{{ route('admin.articles.sunat-existence-types') }}",
            sunatInventoryCatalogs: "{{ route('admin.articles.sunat-inventory-catalogs') }}",
            quickStoreBrand: "{{ route('admin.brands.quick-store') }}",
            quickStorePresentation: "{{ route('admin.presentations.quick-store') }}",
            quickStoreUnit: "{{ route('admin.units.quick-store') }}",
            generateArticleCode: "{{ route('admin.articles.generateCode') }}",
            electronicInvoiceStore: "{{ route('admin.electronic-invoices.store') }}",
            electronicInvoiceShow: "{{ url('admin/electronic-invoices') }}",
            electronicInvoiceUpdate: "{{ url('admin/electronic-invoices') }}",
            electronicInvoiceCustomerPurchaseOrder: "{{ url('admin/electronic-invoices/customer-purchase-order') }}",
            electronicInvoiceSeriesNextNumber: "{{ route('admin.electronic-invoice-series.nextNumber') }}"
        };
        window.electronicInvoiceCompanyEnvironments = @json($companyEnvironments ?? []);
        window.purchaseOrderDocumentTypes = @json($documentTypes);
        window.customerPurchaseOrderCanDispatch = @json(auth()->user()?->can('admin.customer-purchase-orders.dispatch') ?? false);
        window.customerPurchaseOrderCanReverseDispatch = @json(auth()->user()?->can('admin.customer-purchase-orders.dispatch.reverse') ?? false);
        window.customerPurchaseOrderCanViewDispatchDocuments = @json(auth()->user()?->can('admin.customer-purchase-orders.dispatch.documents.view') ?? false);
        window.customerPurchaseOrderCanManageDispatchDocuments = @json(auth()->user()?->can('admin.customer-purchase-orders.dispatch.documents.manage') ?? false);
        window.customerReturnRoutes = {
            list: @json(route('admin.customer-returns.list')),
            base: @json(url('admin/customer-returns')),
            dispatchData: @json(url('admin/customer-returns/dispatches'))
        };
        window.customerReturnPermissions = {
            create: @json(auth()->user()?->can('devoluciones_clientes.crear') ?? false),
            edit: @json(auth()->user()?->can('devoluciones_clientes.editar') ?? false),
            confirm: @json(auth()->user()?->can('devoluciones_clientes.confirmar') ?? false),
            cancel: @json(auth()->user()?->can('devoluciones_clientes.cancelar') ?? false),
            reverse: @json(auth()->user()?->can('devoluciones_clientes.reversar') ?? false),
            documents: @json(auth()->user()?->can('devoluciones_clientes.documentos') ?? false)
        };
        window.customerReturnDocumentTypes = @json(app(\App\Services\CustomerReturnDocumentService::class)->types());
    </script>

    @vite(['resources/js/pages/customer-purchase-order.js'])
@endpush

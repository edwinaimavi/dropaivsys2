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
                    Gestión de pedidos adjudicados desde cotizaciones emitidas.
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
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-primary"></i>
                Lista de Órdenes de Compra
            </h5>
            <small class="text-muted">Pedidos registrados en el sistema</small>
        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableCustomerPurchaseOrder" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>CÓDIGO</th>
                            <th>NRO ORDEN COMPRA</th>
                            <th>COTIZACIÓN</th>
                            <th>CLIENTE</th>
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

    @include('admin.customer-purchase-orders.partials.modal')
    @include('admin.customer-purchase-orders.partials.viewModal')
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
            customerPurchaseOrderQuoteItems: "{{ url('admin/customer-purchase-orders/quote/:id/items') }}",
            customerPurchaseOrderCustomerBranches: "{{ url('admin/customer-purchase-orders/customer/:id/branches') }}"
        };
    </script>

    @vite(['resources/js/pages/customer-purchase-order.js'])
@endpush

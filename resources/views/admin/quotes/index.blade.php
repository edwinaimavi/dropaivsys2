@extends('layouts.app')

@section('subtitle', 'Cotizaciones')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>

                <h1 class="mb-1 font-weight-bold text-dark">

                    <i class="fas fa-file-invoice-dollar text-primary"></i>
                    Cotizaciones

                </h1>

                <small class="text-muted">
                    Gestión y administración de cotizaciones.
                </small>

            </div>

            <div>

                @can('admin.quotes.store')
                <button id="btnCreateQuote" class="btn btn-primary shadow-sm px-4" type="button">

                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva Cotización

                </button>
                @endcan

            </div>

        </div>

        <div class="row">

            <div class="col-12">

                <nav aria-label="breadcrumb">

                    <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">

                        <li class="breadcrumb-item">

                            <a href="{{ route('home') }}" class="text-decoration-none">

                                <i class="fas fa-house-user"></i>
                                Home

                            </a>

                        </li>

                        <li class="breadcrumb-item active">

                            Cotizaciones

                        </li>

                    </ol>

                </nav>

            </div>

        </div>

    </div>


@stop

@section('content_body')


    <div class="card border-0 shadow-lg rounded-xl">

        <div class="card-header bg-white border-0 pt-4 pb-2">

            <div class="d-flex justify-content-between align-items-center flex-wrap">

                <div>

                    <h5 class="mb-1 font-weight-bold text-dark">

                        <i class="fas fa-list text-primary"></i>
                        Lista de Cotizaciones

                    </h5>

                    <small class="text-muted">
                        Cotizaciones registradas en el sistema
                    </small>

                </div>

            </div>

        </div>

        <div class="card-body pt-2">

            <div class="table-responsive">

                <table id="tableQuote" class="table table-hover align-middle text-center w-100">

                    <thead class="bg-light">

                        <tr>

                            <th>#</th>

                            <th>ID</th>

                            <th>N° COTIZACIÓN</th>

                            <th>CLIENTE</th>

                            <th>EMPRESA</th>

                            <th>MONEDA</th>

                            <th>TOTAL</th>

                            <th>ESTADO</th>

                            <th>F. REGISTRO</th>

                            <th width="180px">ACCIONES</th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

    {{-- MODALS --}}
    @include('admin.quotes.partials.modal')

    {{--  @include('admin.quotes.partials.viewModal') --}}


@stop

@push('css')
    <style>
        .rounded-xl {

            border-radius: 18px;

        }

        #tableQuote thead th {

            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;

        }

        #tableQuote tbody td {

            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;

        }

        #tableQuote tbody tr:hover {

            background: #fafafa;
            transition: .2s ease;

        }

        .breadcrumb {

            margin-bottom: 0;

        }

        .btn-primary {

            border-radius: 10px;

        }

        .card {

            overflow: hidden;

        }

        .form-group {

            margin-bottom: .7rem;

        }

        .modal-body {

            padding: .8rem !important;

        }

        .card-body {

            padding: .9rem !important;

        }

        .alert {

            padding: .7rem .9rem;

        }

        .form-control-sm {

            font-size: .82rem;

        }

        .modal-title {

            font-size: 1rem;

        }

        .icon_modal {

            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;

        }

        @media (max-width: 768px) {

            .modal-dialog {

                margin: 5px;
                max-width: 100%;

            }

            .modal-body {

                padding: 10px !important;

            }

            #tableQuote {

                font-size: 12px;

            }

            #tableQuote tbody td {

                padding: 8px 6px;

            }

            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_length {

                text-align: center !important;
                width: 100%;
                margin-bottom: 10px;

            }

            .dataTables_wrapper .dataTables_filter input {

                width: 100% !important;
                margin-left: 0 !important;

            }

            .btn-group-sm>.btn,
            .btn-sm {

                padding: .20rem .35rem;
                font-size: 11px;

            }

        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = {


            quoteList: "{{ route('admin.quotes.list') }}",

            storeQuote: "{{ route('admin.quotes.store') }}",

            updateQuote: "{{ url('admin/quotes') }}",

            deleteQuote: "{{ url('admin/quotes') }}",

            showQuote: "{{ url('admin/quotes') }}",

            quoteCustomerBranches: "{{ route('admin.quotes.customerBranches', ':id') }}",

            quoteCustomerSearch: "{{ route('admin.quotes.customers.search') }}",

            quoteCustomerQuickStore: "{{ route('admin.quotes.customers.quick-store') }}",

            quoteCustomerDocumentConsult: "{{ route('admin.customers.consultar', 'DOC_PLACEHOLDER') }}",

            generateQuoteNumber: "{{ route('admin.quotes.generateNumber') }}",

            quoteMarketStudySearch: "{{ route('admin.quotes.market-studies.search') }}",

            quoteMarketStudyWinners: "{{ route('admin.quotes.marketStudyWinners', ':id') }}",

            quoteArticleSearch: "{{ route('admin.quotes.articles.search') }}",

            quoteArticleGenerateCode: "{{ route('admin.quotes.articles.generate-code') }}",

            quoteArticleQuickStore: "{{ route('admin.quotes.articles.quick-store') }}",

            quoteBrandSearch: "{{ route('admin.quotes.brands.search') }}",

            quoteBrandQuickStore: "{{ route('admin.quotes.brands.quick-store') }}",



        };
    </script>

    @vite(['resources/js/pages/quote.js'])
@endpush

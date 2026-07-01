@extends('layouts.app')

@section('subtitle', 'Estudios de Mercado')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>

                <h1 class="mb-1 font-weight-bold text-dark">

                    <i class="fas fa-chart-line text-success"></i>
                    Estudios de Mercado

                </h1>

                <small class="text-muted">
                    Gestión y administración de estudios de mercado.
                </small>

            </div>

            <div>

                @can('admin.market-studies.store')
                <button id="btnCreateMarketStudy" class="btn btn-success shadow-sm px-4" type="button" data-toggle="modal"
                    data-target="#marketStudyModal">

                    <i class="fas fa-plus-circle mr-1"></i>
                    Nuevo Estudio

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

                            Estudios de Mercado

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

                        <i class="fas fa-list text-success"></i>
                        Lista de Estudios de Mercado

                    </h5>

                    <small class="text-muted">
                        Estudios registrados en el sistema
                    </small>

                </div>

            </div>

        </div>

        <div class="card-body pt-2">

            <div class="table-responsive">

                <table id="tableMarketStudy" class="table table-hover align-middle text-center w-100">

                    <thead class="bg-light">

                        <tr>

                            <th>#</th>

                            <th>ID</th>

                            <th>CÓDIGO</th>

                            <th>DESCRIPCIÓN</th>

                            <th>TÉRMINOS DE REFERENCIA</th>

                            <th>ESTADO</th>

                            <th width="180px">ACCIONES</th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

    {{-- MODALES --}}
    @include('admin.market-studies.partials.modal')
    @include('admin.market-studies.partials.articlePickerModal')
    @include('admin.market-studies.partials.studyQuoteModal')
    @include('admin.market-studies.partials.studyQuotePickerModal')
    @include('admin.market-studies.partials.quoteItemDetailModal')
    @include('admin.market-studies.partials.studyQuoteListModal')
    @include('admin.market-studies.partials.studyQuoteComparisonModal')

    {{-- futuro --}}
    @include('admin.market-studies.partials.viewModal')

@stop


@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableMarketStudy thead th {
            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;
        }

        #tableMarketStudy tbody td {
            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;
        }

        #tableMarketStudy tbody tr:hover {
            background: #fafafa;
            transition: .2s ease;
        }

        .breadcrumb {
            margin-bottom: 0;
        }

        .btn-success {
            border-radius: 10px;
        }

        .card {
            overflow: hidden;
        }

        @media (max-width:768px) {

            .modal-dialog {
                margin: 5px;
                max-width: 100%;
            }

            #tableMarketStudy {
                font-size: 12px;
            }

            #tableMarketStudy tbody td {
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

        }
    </style>
@endpush


@push('js')
    <script>
        window.routes = {

            marketStudyList: "{{ route('admin.market-studies.list') }}",

            marketStudyStore: "{{ route('admin.market-studies.store') }}",

            marketStudyUpdate: "{{ url('admin/market-studies') }}",

            marketStudyDelete: "{{ url('admin/market-studies') }}",

            marketStudyGenerateCode: "{{ route('admin.market-studies.generateCode') }}",

            articlePickerList: "{{ route('admin.articles.listPicker') }}",

            // ==========================================
            // COTIZACIONES
            // ==========================================

            marketStudyQuoteGenerateNumber: "{{ route('admin.market-study-quotes.generateNumber') }}",

            marketStudyQuoteSuppliers: "{{ route('admin.market-study-quotes.suppliers') }}",

            marketStudyQuoteCurrencies: "{{ route('admin.market-study-quotes.currencies') }}",

            marketStudyQuoteExchangeRate: "{{ route('admin.market-study-quotes.exchange-rate') }}",

            marketStudyQuoteSupplierDetail: "{{ url('admin/market-study-quotes/supplier') }}",

            marketStudyQuoteStudyItems: "{{ route('admin.market-study-quotes.study-items', ':id') }}",


            brandSearch: "{{ route('admin.brands.search') }}",
            unitSearch: "{{ route('admin.units.search') }}",

            presentationSearch: "{{ route('admin.presentations.search') }}",


            marketStudyQuoteStore: "{{ route('admin.market-study-quotes.store') }}",
            marketStudyQuoteUpdate: "{{ url('admin/market-study-quotes') }}",
            marketStudyQuoteDelete: "{{ url('admin/market-study-quotes') }}",

            marketStudyQuoteList: "{{ route('admin.market-study-quotes.list', ':id') }}",

            //RUTAS PARA COMPARACION DE COTIZACION
            marketStudyComparisonShow: "{{ route('admin.market-study-comparisons.show', ':id') }}",

            marketStudyComparisonSave: "{{ route('admin.market-study-comparison.save', ':id') }}",

            marketStudyShow: "{{ url('admin/market-studies') }}",
        };
    </script>

    @vite(['resources/js/pages/market-study.js'])
@endpush

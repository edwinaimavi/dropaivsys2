@extends('layouts.app')

@section('subtitle', 'Artículos')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>

                <h1 class="mb-1 font-weight-bold text-dark">

                    <i class="fas fa-boxes text-primary"></i>
                    Artículos

                </h1>

                <small class="text-muted">
                    Gestión y administración de artículos del sistema.
                </small>

            </div>

            <div>

                @can('admin.articles.store')
                <button id="btnCreateArticle" class="btn btn-primary shadow-sm px-4" type="button" data-toggle="modal"
                    data-target="#articleModal">

                    <i class="fas fa-plus-circle mr-1"></i>
                    Nuevo Artículo

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

                            Artículos

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
                        Lista de Artículos

                    </h5>

                    <small class="text-muted">
                        Artículos registrados en el sistema
                    </small>

                </div>

            </div>

        </div>

        <div class="card-body pt-2">

            <div class="table-responsive">

                <table id="tableArticle" class="table table-hover align-middle text-center w-100">

                    <thead class="bg-light">

                        <tr>

                            <th>#</th>

                            <th>ID</th>

                            <th>CÓDIGO</th>

                            <th>TIPO CÓDIGO</th>

                            <th>CÓDIGO INSTITUCIONAL</th>

                            <th>MARCA</th>

                            <th>NOMBRE LEGAL</th>

                            <th>NOMBRE COMERCIAL</th>

                            <th>AFECTO IGV</th>

                            <th>ESTADO</th>

                            <th width="180px">ACCIONES</th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

    {{-- MODALS --}}
    @include('admin.articles.partials.modal')
    @include('admin.articles.partials.modalDocument')


    @include('admin.articles.partials.viewModal')

@stop

@push('css')
    <style>
        .rounded-xl {

            border-radius: 18px;

        }

        #tableArticle thead th {

            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;

        }

        #tableArticle tbody td {

            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;

        }

        #tableArticle tbody tr:hover {

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

            #tableArticle {

                font-size: 12px;

            }

            #tableArticle tbody td {

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

        #articleModal label {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        #articleModal .form-group {
            margin-bottom: .45rem;
        }

        #articleModal .form-control,
        #articleModal .custom-select {
            height: 30px;
            font-size: 12px;
        }

        #articleModal textarea.form-control {
            height: auto;
        }

        #articleModal .card-header {
            padding: .35rem .75rem;
            font-size: 12px;
        }

        #articleModal .card-body {
            padding: .60rem;
        }

        #articleModal .table {
            font-size: 12px;
        }

        #articleModal .modal-body {
            padding: .60rem;
        }

        #articleModal .nav-link {
            padding: .35rem .75rem;
            font-size: 12px;
            font-weight: 600;
        }



        #articleModal .card {
            border: none;
            border-radius: 10px;
        }

        #articleModal .card-header {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .3px;
            border: none;
        }

        #articleModal .card-header i {
            font-size: 12px;
        }

        #articleModal .card-body {
            background: #fff;
        }

        #articleModal label {
            font-size: 11px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 2px;
        }

        #articleModal .form-control,
        #articleModal .custom-select {
            height: 31px;
            font-size: 12px;
        }

        #articleModal textarea.form-control {
            height: auto;
            min-height: 70px;
        }

        #articleModal .form-group {
            margin-bottom: .55rem;
        }

        #articleModal .table {
            font-size: 12px;
        }

        #articleModal .table th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        #articleModal .modal-body {
            background: #f8f9fa;
        }

        .section-title {
            padding: 8px 12px;
            border-radius: 8px 8px 0 0;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            letter-spacing: .3px;
        }

        .section-primary {
            background: #0d6efd;
        }

        .section-info {
            background: #17a2b8;
        }

        .section-secondary {
            background: #6c757d;
        }

        .section-success {
            background: #198754;
        }


        #viewArticleModal .table-responsive {

            overflow-x: auto;
        }

        #viewArticleModal #va_documents_body td {

            vertical-align: middle;
            font-size: 12px;
        }

        #viewArticleModal #va_documents_body td:nth-child(4) {

            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #viewArticleModal .btn-sm {

            padding: .25rem .45rem;
        }

        #viewArticleModal .table th {

            white-space: nowrap;
            font-size: 11px;
        }

        #viewArticleModal .table td {

            white-space: nowrap;
        }

        /*==================================================
                = DOCUMENTOS VIEW ARTICLE
                ==================================================*/

        #viewArticleModal .table thead th {

            border-top: 0;
            border-bottom: 2px solid #e5e7eb;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        #viewArticleModal .table tbody td {

            vertical-align: middle;
            border-top: 1px solid #eef2f7;
        }

        #viewArticleModal #va_documents_body td:nth-child(4) {

            max-width: 280px;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

            font-weight: 500;

            color: #374151;
        }

        #viewArticleModal .table-hover tbody tr:hover {

            background: #f8fafc;
        }

        #viewArticleModal .btn-document {

            width: 34px;

            height: 34px;

            border-radius: 8px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            margin: 0 2px;
        }

        #viewArticleModal .document-badge {

            background: #eef2ff;

            color: #4338ca;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;
        }

        /*=========================================
            = DOCUMENTOS SCROLL
            =========================================*/

        .document-scroll {

            overflow-x: auto;

            overflow-y: hidden;

            scrollbar-width: thin;
        }

        .document-scroll::-webkit-scrollbar {

            height: 10px;
        }

        .document-scroll::-webkit-scrollbar-track {

            background: #edf2f7;

            border-radius: 20px;
        }

        .document-scroll::-webkit-scrollbar-thumb {

            background: #cbd5e1;

            border-radius: 20px;
        }

        .document-scroll::-webkit-scrollbar-thumb:hover {

            background: #94a3b8;
        }

        /*=========================================
    = DATOS ARTICULO COMPACTO
    =========================================*/

        .article-detail-card {

            border-radius: 12px;
        }

        .detail-label {

            display: block;

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .3px;

            color: #64748b;

            margin-bottom: 3px;
        }

        .detail-value {

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 8px;

            padding: 6px 10px;

            min-height: 32px;

            font-size: 12px;

            font-weight: 600;

            color: #1e293b;

            display: flex;

            align-items: center;

            line-height: 1.2;
        }

        .detail-badge {

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1d4ed8;

            border-radius: 8px;

            padding: 6px;

            text-align: center;

            font-size: 11px;

            font-weight: 700;

            line-height: 1.2;
        }

        .detail-observation {

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 8px;

            padding: 10px;

            min-height: 45px;

            font-size: 12px;

            color: #334155;

            line-height: 1.4;
        }

        /* reduce espacios internos */

        .article-detail-card .card-header {

            padding: .65rem 1rem !important;
        }

        .article-detail-card .card-body {

            padding: .85rem !important;
        }

        .article-detail-card .row {

            margin-left: -5px;
            margin-right: -5px;
        }

        .article-detail-card .row>div {

            padding-left: 5px;
            padding-right: 5px;
        }

        .article-detail-card hr {

            margin: .6rem 0;
        }

        /* títulos más elegantes */

        .article-detail-card h6 {

            font-size: 14px;

            font-weight: 700;

            margin: 0;
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = {

            articleList: "{{ route('admin.articles.list') }}",

            storeArticle: "{{ route('admin.articles.store') }}",

            updateArticle: "{{ url('admin/articles') }}",

            deleteArticle: "{{ url('admin/articles') }}",

            generateArticleCode: "{{ route('admin.articles.generateCode') }}",

            subcategoriesByCategory: "{{ route('admin.articles.subcategories', ':id') }}",

            showArticle: "{{ url('admin/articles') }}/:id/show-data",


        };
    </script>

    @vite(['resources/js/pages/article.js'])
@endpush

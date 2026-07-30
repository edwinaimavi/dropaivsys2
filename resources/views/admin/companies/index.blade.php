@extends('layouts.app')

@section('subtitle', 'Empresas')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-building text-info"></i>
                    Empresas
                </h1>
                <small class="text-muted">Gestión de empresas registradas en el sistema.</small>
            </div>

            @can('admin.companies.store')
                <button id="btnCreateCompany" class="btn btn-info shadow-sm px-4" type="button">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva empresa
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
                <li class="breadcrumb-item active">Empresas</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-info"></i>
                Lista de Empresas
            </h5>
            <small class="text-muted">Empresas disponibles para operaciones, compras y facturación.</small>
        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableCompanies" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>LOGO</th>
                            <th>RUC</th>
                            <th>RAZÓN SOCIAL</th>
                            <th>NOMBRE COMERCIAL</th>
                            <th>UBICACIÓN</th>
                            <th>TELÉFONO</th>
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

    @include('admin.companies.partials.modal')
    @include('admin.companies.partials.viewModal')
    @include('admin.companies.partials.bankAccountsModal')
@stop

@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableCompanies thead th {
            padding: 14px 10px;
            border: 0 !important;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableCompanies tbody td {
            padding: 12px 8px;
            border-top: 1px solid #f1f1f1;
            font-size: 13px;
            vertical-align: middle !important;
        }

        #tableCompanies tbody tr:hover {
            background: #fafafa;
        }

        .company-logo-thumb,
        .company-detail-logo {
            object-fit: contain;
            background: #fff;
            border: 1px solid #e9eef0;
        }

        .company-logo-thumb,
        .company-avatar {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .company-logo-thumb {
            padding: 4px;
        }

        .company-avatar {
            background: #eef6f8;
            color: #138496;
            font-size: 18px;
        }

        .company-modal,
        .company-view-modal {
            border-radius: 12px;
            overflow: hidden;
            color: #2e3440;
        }

        .company-modal-dialog {
            width: 94vw;
            max-width: 1120px;
        }

        .company-modal-header {
            background: linear-gradient(135deg, #17a2b8, #138496);
            border-bottom: 0;
            padding: 12px 16px;
        }

        .company-modal-header .modal-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }

        .company-modal-header small {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, .84);
            font-size: 11px;
        }

        .company-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-header-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            flex: 0 0 auto;
        }

        .company-modal-body {
            background: #f7f8fb;
            padding: 14px;
        }

        .company-card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(19, 132, 150, .06) !important;
        }

        .company-card .card-body {
            padding: 12px;
        }

        .company-section-header {
            background: #f8fcfd;
            border-bottom: 1px solid #e5f2f5 !important;
            padding: 8px 12px !important;
        }

        .company-section-header h6 {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .company-section-header small,
        .company-modal label {
            font-size: 10.5px;
        }

        .company-modal label {
            color: #68717a;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .company-modal .form-group {
            margin-bottom: 10px;
        }

        .company-modal .form-control,
        .company-modal .custom-select {
            border-radius: 6px;
            font-size: 12px;
            min-height: 30px;
        }

        .company-modal textarea.form-control {
            height: auto;
            min-height: 54px;
            resize: vertical;
        }

        .company-modal .input-group-sm .btn {
            font-size: 12px;
            font-weight: 600;
        }

        .company-side-icon {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #17a2b8, #138496);
            box-shadow: 0 8px 20px rgba(19, 132, 150, .24);
            font-size: 30px;
        }

        .company-ruc-extra {
            color: #6c757d;
            font-size: 11px;
            font-weight: 600;
        }

        .company-logo-uploader {
            background: #fff;
            border: 1px solid #e5f2f5;
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 8px 18px rgba(19, 132, 150, .05);
        }

        .company-logo-uploader-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .company-logo-uploader-header small {
            display: block;
            color: #7b8794;
            font-size: 10.5px;
            line-height: 1.25;
            margin-top: 2px;
        }

        .company-logo-preview {
            width: 100%;
            min-height: 118px;
            border: 1px dashed #b8dfe6;
            border-radius: 10px;
            background: linear-gradient(180deg, #ffffff, #f8fcfd);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            overflow: hidden;
        }

        .company-logo-preview.is-invalid {
            border-color: #dc3545;
            background: #fff8f8;
        }

        .company-logo-preview img {
            width: 100%;
            max-height: 104px;
            object-fit: contain;
            display: block;
        }

        .company-logo-placeholder {
            color: #138496;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
        }

        .company-logo-placeholder i {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef8fa;
            font-size: 20px;
        }

        .company-logo-placeholder span {
            color: #6c757d;
            font-size: 11px;
            font-weight: 700;
        }

        .company-logo-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .company-logo-actions .btn {
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .company-logo-file-name {
            min-width: 0;
            color: #495057;
            font-size: 11px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .company-logo-help {
            display: block;
            color: #7b8794;
            font-size: 10px;
            line-height: 1.25;
            margin-top: 6px;
        }

        .company-modal-footer {
            background: #fff;
            border-top: 1px solid #edf1f3;
            padding: 10px 14px;
        }

        .company-detail-logo {
            width: 86px;
            height: 86px;
            border-radius: 16px;
            padding: 6px;
        }

        .company-view-header {
            flex: 0 0 auto;
            background: linear-gradient(120deg, #ffffff, #f2f8f6);
            border-bottom: 1px solid #e6edf0 !important;
            padding: 18px 22px;
        }

        .company-view-header .modal-title {
            color: #263238;
            font-size: 20px;
            line-height: 1.2;
        }

        .company-view-dialog {
            width: calc(100% - 32px);
            max-width: 1180px;
        }

        .company-view-modal {
            max-height: calc(100vh - 32px);
            border-radius: 18px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .company-view-body {
            flex: 1 1 auto;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 18px;
            background: #f4f7f6;
        }

        .company-view-eyebrow {
            color: #16845b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .1em;
        }

        .company-view-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            color: #65727c;
            font-size: 12px;
        }

        .company-view-close {
            width: 38px;
            height: 38px;
            margin: 0 !important;
            padding: 0 !important;
            border-radius: 50%;
            background: #eef3f1 !important;
        }

        .company-view-logo-box,
        .company-detail-avatar {
            width: 86px;
            height: 86px;
            border-radius: 16px;
            background: #eef6f8;
            color: #138496;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .company-view-logo-box .company-detail-logo {
            width: 100%;
            height: 100%;
        }

        .company-view-logo-caption {
            display: none;
        }

        .company-view-section {
            padding: 18px;
            margin-bottom: 16px;
            border: 1px solid #e5ece9;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 7px 20px rgba(32, 53, 45, .045);
        }

        .company-view-section-title {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .company-view-section-title h5 {
            margin: 0;
            color: #293630;
            font-size: 15px;
            font-weight: 700;
        }

        .company-view-section-title small {
            color: #7a8781;
        }

        .company-view-section-icon {
            width: 38px;
            height: 38px;
            margin-right: 10px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #198754;
            background: rgba(25, 135, 84, .1);
        }

        .company-info-block {
            height: 100%;
            border: 1px solid #e8eeeb;
            border-radius: 10px;
            padding: 12px;
            background: #fbfcfc;
            min-height: 62px;
        }

        .company-info-block small {
            color: #7b8794;
            font-weight: 700;
            font-size: 10.5px;
            text-transform: uppercase;
        }

        .company-info-block div {
            color: #2e3440;
            font-weight: 600;
            word-break: break-word;
        }

        .company-address-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 10px;
            color: #37443e;
            background: #f7faf9;
            border: 1px solid #e8eeeb;
        }

        .company-address-card i {
            color: #198754;
            font-size: 18px;
        }

        .company-view-bank-table {
            border: 1px solid #e5ebe8;
            border-radius: 10px;
        }

        .company-view-bank-table th {
            border-top: 0;
            background: #f5f8f7;
            color: #6b7771;
            font-size: 10px;
            letter-spacing: .035em;
            white-space: nowrap;
        }

        .company-view-bank-table td {
            color: #35413c;
            font-size: 12px;
            vertical-align: middle;
        }

        .company-bank-empty {
            padding: 28px 16px;
            border: 1px dashed #cddbd5;
            border-radius: 12px;
            text-align: center;
            color: #68766f;
            background: #fafcfb;
        }

        .company-bank-empty span {
            width: 48px;
            height: 48px;
            margin: 0 auto 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #198754;
            background: rgba(25, 135, 84, .1);
            font-size: 19px;
        }

        .company-bank-empty strong,
        .company-bank-empty small {
            display: block;
        }

        .min-width-0 {
            min-width: 0;
        }

        @media (max-width: 991.98px) {
            .company-modal-dialog {
                width: auto;
                max-width: calc(100vw - 16px);
                margin: .5rem auto;
            }

            .company-view-dialog {
                width: auto;
                max-width: calc(100vw - 16px);
                margin: 8px auto;
            }

            .company-view-modal {
                max-height: calc(100vh - 16px);
            }
        }

        @media (max-width: 575.98px) {
            .company-view-header {
                padding: 14px;
            }

            .company-view-logo-box {
                width: 64px;
                height: 64px;
            }

            .company-view-header .modal-title {
                font-size: 16px;
            }

            .company-view-body,
            .company-view-section {
                padding: 12px;
            }
        }

        .breadcrumb {
            margin-bottom: 0;
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            companiesList: "{{ route('admin.companies.list') }}",
            companiesStore: "{{ route('admin.companies.store') }}",
            companiesShow: "{{ url('admin/companies') }}",
            companiesUpdate: "{{ url('admin/companies') }}",
            companiesDelete: "{{ url('admin/companies') }}",
            companiesConsultarRuc: "{{ url('admin/document-lookup/ruc') }}",
            companyBankAccountsBase: "{{ url('admin/companies') }}"
        });
    </script>
    @vite(['resources/js/pages/company.js'])
@endpush

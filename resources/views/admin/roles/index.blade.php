@extends('layouts.app')

@section('subtitle', 'Roles')

@section('header')
    <div class="container-fluid">
        <div class="roles-page-heading d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div class="d-flex align-items-center">
                <div class="roles-heading-icon mr-3">
                    <i class="fas fa-user-tag"></i>
                </div>
                <div>
                    <h1 class="mb-1 font-weight-bold text-dark">Roles de Usuario</h1>
                    <small class="text-muted">Gesti&oacute;n de roles y permisos del sistema</small>
                </div>
            </div>

            @can('admin.roles.store')
                <button class="btn btn-success btn-sm shadow-sm roles-create-btn mt-2 mt-md-0" type="button"
                    data-toggle="modal" data-target="#roleModal">
                    <i class="fas fa-plus mr-1"></i>
                    Nuevo Rol
                </button>
            @endcan
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="text-decoration-none">
                        <i class="fas fa-home"></i>
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Roles de Usuario</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="roles-dashboard">
        <div class="card border-0 shadow-sm roles-table-card">
            <div class="card-header border-0">
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark">
                        <i class="fas fa-list text-success mr-1"></i>
                        Listado de roles
                    </h5>
                    <small class="text-muted">Administra los perfiles de acceso disponibles en el sistema</small>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive roles-table-wrap">
                    <table id="tableRole" class="table table-hover table-sm text-center w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>Rol</th>
                                <th>Guard</th>
                                <th>Permisos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        @include('admin.roles.partials.modal')
    </div>
@stop

@push('css')
    <style>
        .roles-dashboard {
            color: #1f2937;
        }

        .roles-page-heading h1 {
            font-size: 25px;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .roles-page-heading small {
            font-size: 12px;
        }

        .roles-heading-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #16a34a, #0f9488);
            box-shadow: 0 10px 22px rgba(15, 148, 136, .22);
            font-size: 18px;
            flex: 0 0 auto;
        }

        .roles-create-btn {
            min-width: 116px;
            border-radius: 9px;
            font-weight: 800;
        }

        .roles-table-card,
        .roles-modal-card,
        .roles-permission-card,
        .roles-side-panel {
            border: 1px solid #edf2f7 !important;
            border-radius: 14px !important;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .07) !important;
        }

        .roles-table-card .card-header {
            padding: 15px 17px 8px;
            background: #fff !important;
        }

        .roles-table-card .card-body {
            padding: 10px 14px 14px;
        }

        .roles-table-wrap {
            border: 1px solid #edf2f7;
            border-radius: 12px;
            overflow-x: auto;
            background: #fff;
        }

        #tableRole {
            margin-bottom: 0 !important;
        }

        #tableRole thead th {
            padding: 11px 8px;
            border: 0 !important;
            border-bottom: 1px solid #e7eef0 !important;
            background: #f8fafc;
            color: #64748b;
            font-size: 10.5px;
            font-weight: 850;
            letter-spacing: .18px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        #tableRole tbody td {
            padding: 10px 8px;
            border-top: 1px solid #f0f3f4;
            color: #334155;
            font-size: 12.5px;
            vertical-align: middle !important;
        }

        #tableRole tbody tr:hover {
            background: #f8fcfb;
        }

        .roles-name-cell {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }

        .roles-name-icon {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e7f7f4;
            color: #0f9488;
        }

        .roles-name-text {
            display: block;
            color: #1f2937;
            font-weight: 850;
            line-height: 1.15;
        }

        .roles-guard-pill,
        .roles-permissions-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .roles-guard-pill {
            background: #f1f5f9;
            color: #475569;
        }

        .roles-permissions-pill {
            background: #e7f7f4;
            color: #0f766e;
        }

        .roles-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .roles-action-btn {
            width: 31px;
            height: 31px;
            padding: 0;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .roles-modal .modal-dialog {
            max-width: 1120px;
        }

        .roles-modal .modal-content {
            border-radius: 16px !important;
            overflow: hidden;
        }

        .roles-modal .modal-header {
            padding: 15px 18px;
            background: linear-gradient(135deg, #0f7a38, #0f9488) !important;
            color: #fff;
        }

        .roles-modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .roles-modal-title-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .16);
            color: #fff;
        }

        .roles-modal .modal-title {
            color: #fff;
            font-size: 16px;
            font-weight: 850;
        }

        .roles-modal .modal-header small {
            color: rgba(255, 255, 255, .78);
            font-size: 11px;
        }

        .roles-modal .close {
            color: #fff;
            text-shadow: none;
            opacity: .9;
        }

        .roles-modal .modal-body {
            padding: 14px;
            background: #f8fafc !important;
        }

        .roles-side-panel {
            height: 100%;
            padding: 16px;
            background: linear-gradient(180deg, #ffffff, #f8fdfb);
        }

        .roles-side-icon {
            width: 54px;
            height: 54px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e7f7f4;
            color: #0f9488;
            font-size: 22px;
        }

        .roles-summary-box {
            padding: 12px;
            border-radius: 12px;
            background: #f1f5f9;
        }

        .roles-summary-box span {
            display: block;
            color: #64748b;
            font-size: 10px;
            font-weight: 850;
            letter-spacing: .2px;
            text-transform: uppercase;
        }

        .roles-summary-box strong {
            display: block;
            margin-top: 4px;
            color: #0f766e;
            font-size: 20px;
            font-weight: 900;
        }

        .roles-modal-card .card-header,
        .roles-permission-card .card-header {
            padding: 13px 15px 8px;
            background: #fff !important;
        }

        .roles-modal-card .card-body,
        .roles-permission-card .card-body {
            padding: 12px 15px 15px;
        }

        .roles-toolbar {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .roles-search-wrap {
            position: relative;
            min-width: 260px;
            flex: 1 1 320px;
        }

        .roles-search-wrap i {
            position: absolute;
            left: 11px;
            top: 50%;
            color: #94a3b8;
            transform: translateY(-50%);
        }

        .roles-search-wrap input {
            padding-left: 32px;
        }

        .roles-permission-groups {
            max-height: 430px;
            padding-right: 4px;
            overflow-y: auto;
        }

        .roles-permission-group {
            margin-bottom: 12px;
            border: 1px solid #edf2f7;
            border-radius: 13px;
            background: #fff;
            overflow: hidden;
        }

        .roles-permission-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }

        .roles-permission-group-title {
            color: #1f2937;
            font-size: 13px;
            font-weight: 850;
        }

        .roles-permission-group-count {
            color: #64748b;
            font-size: 11px;
            font-weight: 750;
        }

        .roles-permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(225px, 1fr));
            gap: 8px;
            padding: 10px;
        }

        .roles-permission-item {
            min-height: 52px;
            padding: 9px 10px;
            border: 1px solid #e6eef3;
            border-radius: 11px;
            background: #fbfdff;
            transition: border-color .16s ease, background .16s ease, box-shadow .16s ease;
        }

        .roles-permission-item:hover {
            border-color: rgba(15, 148, 136, .35);
            background: #f8fffd;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .05);
        }

        .roles-permission-item .custom-control-label {
            color: #334155;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.25;
            text-transform: none;
            letter-spacing: 0;
            cursor: pointer;
        }

        .roles-permission-item small {
            display: block;
            margin-top: 2px;
            color: #94a3b8;
            font-size: 10.5px;
            font-weight: 700;
        }

        .roles-permission-item .custom-control-input:checked ~ .custom-control-label::before {
            border-color: #0f9488;
            background: #0f9488;
        }

        .roles-permission-empty {
            display: none;
            padding: 18px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            color: #64748b;
            text-align: center;
            font-size: 12px;
            font-weight: 750;
        }

        .roles-modal .modal-footer {
            padding: 12px 18px;
            background: #fff;
        }

        @media (max-width: 767.98px) {
            .roles-page-heading h1 {
                font-size: 21px;
            }

            .roles-heading-icon {
                width: 40px;
                height: 40px;
            }

            .roles-search-wrap {
                min-width: 100%;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            storeRole: "{{ route('admin.roles.store') }}",
            rolesList: "{{ route('admin.roles.list') }}",
            deleteRole: "{{ url('admin/roles') }}"
        });
    </script>
    @vite(['resources/js/pages/roles.js'])
@endpush

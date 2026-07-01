@extends('layouts.app')

@section('subtitle', 'Usuarios')

@section('header')
    <div class="container-fluid">
        <div class="users-page-heading d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div class="d-flex align-items-center">
                <div class="users-heading-icon mr-3">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div>
                    <h1 class="mb-1 font-weight-bold text-dark">Usuarios</h1>
                    <small class="text-muted">Gesti&oacute;n y administraci&oacute;n de accesos al sistema</small>
                </div>
            </div>

            @can('admin.users.store')
                <button id="btnCreateUser" class="btn btn-success btn-sm shadow-sm users-create-btn mt-2 mt-md-0"
                    type="button" data-toggle="modal" data-target="#userModal">
                    <i class="fas fa-plus mr-1"></i>
                    Nuevo Usuario
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
                <li class="breadcrumb-item active">Usuarios</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="users-dashboard">
        <div class="card border-0 shadow-sm users-table-card">
            <div class="card-header border-0">
                <div>
                    <h5 class="mb-1 font-weight-bold text-dark">
                        <i class="fas fa-list text-success mr-1"></i>
                        Listado de usuarios
                    </h5>
                    <small class="text-muted">Usuarios registrados, estados y roles asignados</small>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive users-table-wrap">
                    <table id="tableUser" class="table table-hover table-sm text-center w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>DNI</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Celular</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('admin.users.partials.modal')
        @include('admin.users.partials.viewModal')
    </div>
@stop

@push('css')
    <style>
        .users-dashboard {
            color: #1f2937;
        }

        .users-page-heading h1 {
            font-size: 25px;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .users-page-heading small {
            font-size: 12px;
        }

        .users-heading-icon {
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

        .users-create-btn {
            min-width: 132px;
            border-radius: 9px;
            font-weight: 800;
        }

        .users-table-card,
        .users-modal-card,
        .users-side-panel,
        .users-detail-card,
        .users-detail-profile {
            border: 1px solid #edf2f7 !important;
            border-radius: 14px !important;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .07) !important;
        }

        .users-table-card .card-header {
            padding: 15px 17px 8px;
            background: #fff !important;
        }

        .users-table-card .card-body {
            padding: 10px 14px 14px;
        }

        .users-table-wrap {
            border: 1px solid #edf2f7;
            border-radius: 12px;
            overflow-x: auto;
            background: #fff;
        }

        #tableUser {
            margin-bottom: 0 !important;
        }

        #tableUser thead th {
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

        #tableUser tbody td {
            padding: 10px 8px;
            border-top: 1px solid #f0f3f4;
            color: #334155;
            font-size: 12.5px;
            vertical-align: middle !important;
        }

        #tableUser tbody tr:hover {
            background: #f8fcfb;
        }

        .users-name-cell {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 180px;
            text-align: left;
        }

        .users-name-avatar {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e7f7f4;
            color: #0f9488;
            font-size: 13px;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .users-name-main {
            display: block;
            color: #1f2937;
            font-weight: 850;
            line-height: 1.15;
        }

        .users-name-sub {
            display: block;
            color: #94a3b8;
            font-size: 10.5px;
            font-weight: 700;
        }

        .users-email-cell {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #475569;
            font-weight: 700;
            white-space: nowrap;
        }

        .users-role-chip,
        .users-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 11px;
            font-weight: 850;
            white-space: nowrap;
        }

        .users-role-chip {
            background: #e7f7f4;
            color: #0f766e;
        }

        .users-role-chip-empty {
            background: #f1f5f9;
            color: #64748b;
        }

        .users-status-active {
            background: #dcfce7;
            color: #166534;
        }

        .users-status-inactive {
            background: #fee2e2;
            color: #b91c1c;
        }

        .users-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .users-action-btn {
            width: 31px;
            height: 31px;
            padding: 0;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .users-modal .modal-dialog,
        .users-detail-modal .modal-dialog {
            max-width: 1120px;
        }

        .users-modal .modal-content,
        .users-detail-modal .modal-content {
            border-radius: 16px !important;
            overflow: hidden;
        }

        .users-modal .modal-header,
        .users-detail-modal .modal-header {
            padding: 15px 18px;
            background: linear-gradient(135deg, #0f7a38, #0f9488) !important;
            color: #fff;
        }

        .users-modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .users-modal-title-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .16);
            color: #fff;
        }

        .users-modal .modal-title,
        .users-detail-modal .modal-title {
            color: #fff;
            font-size: 16px;
            font-weight: 850;
        }

        .users-modal .modal-header small,
        .users-detail-modal .modal-header small {
            color: rgba(255, 255, 255, .78);
            font-size: 11px;
        }

        .users-modal .close,
        .users-detail-modal .close {
            color: #fff;
            text-shadow: none;
            opacity: .9;
        }

        .users-modal .modal-body,
        .users-detail-modal .modal-body {
            padding: 14px !important;
            background: #f8fafc !important;
        }

        .users-side-panel {
            height: 100%;
            padding: 16px;
            background: linear-gradient(180deg, #ffffff, #f8fdfb);
        }

        .users-side-icon {
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

        .users-side-avatar {
            width: 154px;
            height: 154px;
            border-radius: 18px;
            overflow: hidden;
            border: 6px solid #fff;
            background: #f1f5f9;
            box-shadow: 0 12px 24px rgba(15, 23, 42, .10);
        }

        .users-side-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .users-photo-button {
            position: relative;
            overflow: hidden;
        }

        .users-modal-card .card-header {
            padding: 13px 15px 8px;
            background: #fff !important;
        }

        .users-modal-card .card-body {
            padding: 12px 15px 15px;
        }

        .users-modal label {
            font-size: 10.5px;
            font-weight: 850;
        }

        .users-password-help {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
        }

        .users-detail-avatar {
            width: 114px;
            height: 114px;
            overflow: hidden;
            border-radius: 22px;
            color: #fff;
            background: linear-gradient(135deg, #16a34a, #0f9488);
            border: 6px solid #fff;
            box-shadow: 0 12px 26px rgba(15, 148, 136, .20);
            font-size: 42px;
        }

        .users-detail-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .users-detail-field {
            height: 100%;
            padding: 11px 13px;
            border: 1px solid #edf2f7;
            border-radius: 11px;
            background: #fbfdff;
        }

        .users-detail-label,
        .users-detail-date small {
            display: block;
            margin-bottom: 3px;
            color: #64748b;
            font-size: 10.5px;
            font-weight: 850;
            letter-spacing: .22px;
            text-transform: uppercase;
        }

        .users-detail-value {
            display: block;
            color: #263445;
            font-size: 12.5px;
            font-weight: 750;
        }

        .users-detail-date {
            display: flex;
            align-items: center;
            min-height: 62px;
            padding: 11px 14px;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            background: #fff;
        }

        .users-detail-date i {
            font-size: 22px;
        }

        .users-detail-date strong {
            display: block;
            color: #344050;
            font-size: 12px;
        }

        @media (max-width: 767.98px) {
            .users-page-heading h1 {
                font-size: 21px;
            }

            .users-heading-icon {
                width: 40px;
                height: 40px;
            }

            .users-modal .modal-dialog,
            .users-detail-modal .modal-dialog {
                margin: 8px;
            }

            .users-side-avatar {
                width: 126px;
                height: 126px;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            storeUser: "{{ route('admin.users.store') }}",
            usersList: "{{ route('admin.users.list') }}",
            deleteUser: "{{ url('admin/users') }}"
        });

        function previewImage(event, querySelector) {
            let input = event.target;
            let imgPreview = document.querySelector(querySelector);
            if (!input.files.length) return;
            let file = input.files[0];
            let objectURL = URL.createObjectURL(file);
            imgPreview.src = objectURL;
        }
    </script>
    @vite(['resources/js/pages/user.js'])
@endpush

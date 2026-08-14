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

        .users-principal-chip {
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
        }

        .users-principal-notice {
            padding: 10px 13px;
            border: 1px solid #fde68a;
            border-radius: 11px;
            background: #fffbeb;
            color: #854d0e;
            font-size: 12px;
            font-weight: 700;
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

        .users-modal .modal-dialog {
            max-width: 1120px;
        }

        .users-detail-modal .modal-dialog {
            max-width: 980px;
        }

        .users-modal .modal-content,
        .users-detail-modal .modal-content {
            border-radius: 16px !important;
            overflow: hidden;
        }

        .users-modal .modal-header {
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

        .users-modal .modal-title {
            color: #fff;
            font-size: 16px;
            font-weight: 850;
        }

        .users-modal .modal-header small {
            color: rgba(255, 255, 255, .78);
            font-size: 11px;
        }

        .users-modal .close {
            color: #fff;
            text-shadow: none;
            opacity: .9;
        }

        .users-modal .modal-body {
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

        .users-detail-modal .modal-content {
            border: 1px solid #e7eeec !important;
            border-radius: 18px !important;
            background: #fff !important;
            box-shadow: 0 28px 72px rgba(15, 23, 42, .20) !important;
        }

        .users-detail-modal .users-detail-header {
            min-height: 72px;
            padding: 14px 18px !important;
            border-bottom: 1px solid #e8efee !important;
            background: linear-gradient(180deg, #fff, #fbfcfd) !important;
        }

        .users-detail-heading {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .users-detail-heading > span:last-child,
        .users-detail-heading .modal-title,
        .users-detail-heading small {
            display: block;
        }

        .users-detail-heading-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            border-radius: 12px;
            background: #e8f7f2;
            color: #0f8f61;
            font-size: 17px;
        }

        .users-detail-heading .modal-title {
            color: #0f172a !important;
            font-size: 16px;
            font-weight: 850;
        }

        .users-detail-heading small {
            margin-top: 2px;
            color: #64748b !important;
            font-size: 10.5px;
        }

        .users-detail-modal .modal-body {
            padding: 16px !important;
            background: #f7fafb !important;
        }

        .users-detail-modal .modal-footer {
            padding: 10px 16px;
            border-top: 1px solid #edf2f1 !important;
            background: #fff !important;
        }

        .users-detail-summary {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 420px;
            height: 100%;
            padding: 23px 17px 18px;
            border: 1px solid #e5eeec;
            border-radius: 15px;
            background:
                radial-gradient(circle at 50% 0, rgba(20, 184, 166, .14), transparent 11rem),
                #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .07);
            text-align: center;
        }

        .users-detail-modal .users-detail-avatar {
            width: 148px;
            height: 148px;
            border: 5px solid #fff;
            border-radius: 50%;
            background: linear-gradient(145deg, #dff8f1, #c9f0e7);
            color: #0f766e;
            box-shadow: 0 17px 36px rgba(15, 118, 110, .18);
            font-family: "Poppins", sans-serif;
            font-size: 40px;
            font-weight: 850;
        }

        .users-detail-avatar-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .users-detail-summary-name {
            max-width: 100%;
            margin: 0 0 8px;
            color: #1f2937;
            font-size: 17px;
            font-weight: 850;
            overflow-wrap: anywhere;
        }

        .users-detail-contact-list {
            width: 100%;
            margin-top: 18px;
            padding-top: 15px;
            border-top: 1px solid #edf2f1;
        }

        .users-detail-contact-list > div {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 9px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
        }

        .users-detail-contact-list i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            flex: 0 0 auto;
            border-radius: 8px;
            background: #eff8f5;
            color: #0f8f72;
        }

        .users-detail-contact-list span {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .users-detail-tabs {
            display: flex;
            flex-wrap: nowrap;
            gap: 6px;
            margin-bottom: 11px;
            padding: 5px;
            border: 1px solid #e6eeec;
            border-radius: 12px;
            background: #fff;
        }

        .users-detail-tabs .nav-item {
            flex: 1 1 0;
        }

        .users-detail-tabs .nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 7px;
            border-radius: 8px;
            color: #64748b;
            font-size: 10.8px;
            font-weight: 800;
            white-space: nowrap;
        }

        .users-detail-tabs .nav-link.active {
            background: linear-gradient(135deg, #0f8f61, #0f9488);
            color: #fff;
            box-shadow: 0 8px 18px rgba(15, 143, 97, .18);
        }

        .users-detail-tab-content {
            min-height: 358px;
            padding: 17px;
            border: 1px solid #e6eeec;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 13px 32px rgba(15, 23, 42, .055);
        }

        .users-detail-section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 11px;
            border-bottom: 1px solid #edf2f1;
        }

        .users-detail-section-title strong,
        .users-detail-section-title small {
            display: block;
        }

        .users-detail-section-title strong {
            color: #263445;
            font-size: 13px;
            font-weight: 850;
        }

        .users-detail-section-title small {
            margin-top: 2px;
            color: #8492a6;
            font-size: 10px;
        }

        .users-detail-section-title > i {
            color: #22a98a;
            font-size: 19px;
        }

        .users-detail-grid > [class*="col-"] {
            margin-bottom: 11px;
        }

        .users-detail-modal .users-detail-field {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 64px;
            height: 100%;
            padding: 10px 12px;
            border: 1px solid #e8efee;
            border-radius: 11px;
            background: #fbfdfd;
        }

        .users-detail-modal .users-detail-field > i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 33px;
            height: 33px;
            flex: 0 0 auto;
            border-radius: 9px;
            background: #e9f8f4;
            color: #0f8f72;
            font-size: 13px;
        }

        .users-detail-modal .users-detail-field > span {
            min-width: 0;
        }

        .users-detail-modal .users-detail-field small,
        .users-detail-modal .users-detail-field strong {
            display: block;
        }

        .users-detail-modal .users-detail-field small {
            margin-bottom: 3px;
            color: #8492a6;
            font-size: 9.3px;
            font-weight: 850;
            letter-spacing: .18px;
            text-transform: uppercase;
        }

        .users-detail-modal .users-detail-field strong {
            color: #334155;
            font-size: 11.5px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .users-detail-principal-field.is-principal {
            border-color: #fde3a7;
            background: #fffbeb;
        }

        .users-detail-principal-field.is-principal > i {
            background: #fef3c7;
            color: #a16207;
        }

        .users-detail-historical {
            color: #94a3b8 !important;
            font-weight: 650 !important;
            font-style: italic;
        }

        .theme-dark .users-detail-modal .users-detail-header,
        .theme-dark .users-detail-modal .modal-footer,
        .theme-dark .users-detail-summary,
        .theme-dark .users-detail-tabs,
        .theme-dark .users-detail-tab-content,
        .theme-dark .users-detail-modal .users-detail-field {
            border-color: var(--app-border) !important;
            background: var(--app-surface-soft) !important;
        }

        .theme-dark .users-detail-heading .modal-title,
        .theme-dark .users-detail-summary-name,
        .theme-dark .users-detail-section-title strong,
        .theme-dark .users-detail-modal .users-detail-field strong {
            color: var(--app-text) !important;
        }

        .theme-dark .users-detail-heading small,
        .theme-dark .users-detail-section-title small,
        .theme-dark .users-detail-modal .users-detail-field small,
        .theme-dark .users-detail-contact-list {
            color: var(--app-muted) !important;
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

            .users-detail-summary {
                min-height: auto;
            }

            .users-detail-tabs {
                overflow-x: auto;
            }

            .users-detail-tabs .nav-item {
                min-width: 132px;
            }

            .users-detail-tab-content {
                min-height: 0;
                padding: 13px;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            storeUser: "{{ route('admin.users.store') }}",
            usersList: "{{ route('admin.users.list') }}",
            deleteUser: "{{ url('admin/users') }}",
            showUser: "{{ url('admin/users') }}",
            userDniLookup: "{{ route('admin.document-lookup.dni', 'DNI_PLACEHOLDER') }}"
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

@extends('layouts.app')

@section('subtitle', 'Configuración Electrónica')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-cogs text-warning"></i>
                    Configuración Electrónica
                </h1>
                <small class="text-muted">Credenciales, ambiente y datos de emisor para facturación electrónica.</small>
            </div>
            @can('admin.electronic-invoice-settings.store')
                <button type="button" class="btn btn-warning btn-sm shadow-sm mt-2 mt-md-0" data-toggle="modal" data-target="#electronicInvoiceSettingModal">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva Configuración
                </button>
            @endcan
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-decoration-none">
                        <i class="fas fa-home"></i>
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.electronic-invoices.index') }}" class="text-decoration-none">Facturación Electrónica</a>
                </li>
                <li class="breadcrumb-item active">Configuración Electrónica</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-table text-warning mr-1"></i>
                Configuraciones registradas
            </h5>
            <small class="text-muted">Parámetros por empresa, proveedor y ambiente.</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableElectronicInvoiceSetting" class="table table-hover table-sm text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Ambiente</th>
                            <th>RUC</th>
                            <th>Razón social</th>
                            <th>Activo</th>
                            <th width="110">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="electronicInvoiceSettingModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">
                <form id="electronicInvoiceSettingForm">
                    @csrf
                    <input type="hidden" id="setting_id">

                    <div class="modal-header border-0 bg-light">
                        <div>
                            <h5 class="modal-title font-weight-bold mb-0" id="electronicInvoiceSettingModalTitle">
                                Nueva Configuración
                            </h5>
                            <small class="text-muted">Registra credenciales y datos del emisor.</small>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <div class="p-3 bg-light rounded h-100">
                                    <div class="text-center mb-3">
                                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-warning text-white" style="width:64px;height:64px;">
                                            <i class="fas fa-plug fa-lg"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block">Proveedor</small>
                                    <strong>APIs Perú / SUNAT</strong>
                                    <hr>
                                    <small class="text-muted d-block">Alcance</small>
                                    <span>Credenciales, empresa y ambiente</span>
                                </div>
                            </div>
                            <div class="col-lg-9">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Empresa</label>
                                        <select class="form-control form-control-sm" id="setting_company_id" name="company_id">
                                            <option value="">Sin empresa vinculada</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Proveedor <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="setting_provider" name="provider" value="apisperu">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Ambiente <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="setting_environment" name="environment">
                                            <option value="beta">Beta</option>
                                            <option value="production">Producción</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>URL API</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_api_base_url" name="api_base_url">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>RUC</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_ruc" name="ruc" maxlength="20">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Activo</label>
                                        <select class="form-control form-control-sm" id="setting_is_active" name="is_active">
                                            <option value="1">Sí</option>
                                            <option value="0">No</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Razón social</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_business_name" name="business_name">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Nombre comercial</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_trade_name" name="trade_name">
                                    </div>
                                    <div class="form-group col-md-12">
                                        <label>Dirección</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_address" name="address">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Ubigeo</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_ubigeo" name="ubigeo">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Departamento</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_department" name="department">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Provincia</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_province" name="province">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Distrito</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_district" name="district">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Token API</label>
                                        <textarea class="form-control form-control-sm" id="setting_api_token" name="api_token" rows="2"></textarea>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Token usuario</label>
                                        <textarea class="form-control form-control-sm" id="setting_user_token" name="user_token" rows="2"></textarea>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Usuario SOL</label>
                                        <input type="text" class="form-control form-control-sm" id="setting_sol_user" name="sol_user">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Clave SOL</label>
                                        <input type="password" class="form-control form-control-sm" id="setting_sol_password" name="sol_password">
                                    </div>
                                </div>
                                <div id="setting_general_errors" class="alert alert-danger d-none mt-2 mb-0"></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>
                            Cerrar
                        </button>
                        @canany(['admin.electronic-invoice-settings.store', 'admin.electronic-invoice-settings.update'])
                            <button type="submit" class="btn btn-warning btn-sm text-white" id="btnSaveElectronicInvoiceSetting">
                                <i class="fas fa-save mr-1"></i>
                                Guardar Configuración
                            </button>
                        @endcanany
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            electronicInvoiceSettingList: "{{ route('admin.electronic-invoice-settings.list') }}",
            electronicInvoiceSettingStore: "{{ route('admin.electronic-invoice-settings.store') }}",
            electronicInvoiceSettingBase: "{{ url('admin/electronic-invoice-settings') }}"
        });
    </script>
    @vite(['resources/js/pages/electronic-invoice-setting.js'])
@endpush

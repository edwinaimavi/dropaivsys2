@extends('layouts.app')

@section('subtitle', 'Series Electrónicas')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-list-ol text-info"></i>
                    Series Electrónicas
                </h1>
                <small class="text-muted">Control de series, correlativos y ambientes de emisión.</small>
            </div>
            @can('admin.electronic-invoice-series.store')
                <button type="button" class="btn btn-info btn-sm shadow-sm mt-2 mt-md-0" data-toggle="modal" data-target="#electronicInvoiceSeriesModal">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva Serie
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
                <li class="breadcrumb-item active">Series Electrónicas</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-table text-info mr-1"></i>
                Listado de series
            </h5>
            <small class="text-muted">Series registradas por empresa, documento y ambiente.</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableElectronicInvoiceSeries" class="table table-hover table-sm text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>Empresa</th>
                            <th>Tipo</th>
                            <th>Serie</th>
                            <th>Actual</th>
                            <th>Siguiente</th>
                            <th>Ambiente</th>
                            <th>Estado</th>
                            <th width="120">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="electronicInvoiceSeriesModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">
                <form id="electronicInvoiceSeriesForm">
                    @csrf
                    <input type="hidden" id="series_id">

                    <div class="modal-header border-0 bg-light">
                        <div>
                            <h5 class="modal-title font-weight-bold mb-0" id="electronicInvoiceSeriesModalTitle">
                                Nueva Serie
                            </h5>
                            <small class="text-muted">Define correlativos para comprobantes electrónicos.</small>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded h-100">
                                    <div class="text-center mb-3">
                                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-info text-white" style="width:64px;height:64px;">
                                            <i class="fas fa-file-invoice fa-lg"></i>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block">Módulo</small>
                                    <strong>Series Electrónicas</strong>
                                    <hr>
                                    <small class="text-muted d-block">Uso</small>
                                    <span>Factura, boleta y notas electrónicas</span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label>Empresa <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="series_company_id" name="company_id">
                                            <option value="">Seleccione</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback" id="series_company_id-error"></span>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Tipo documento <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="series_document_type" name="document_type">
                                            <option value="01">Factura</option>
                                            <option value="03">Boleta</option>
                                            <option value="07">Nota de Crédito</option>
                                            <option value="08">Nota de Débito</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Ambiente <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" id="series_environment" name="environment">
                                            <option value="internal">Interno</option>
                                            <option value="beta">Beta</option>
                                            <option value="production">Producción</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Serie <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm text-uppercase" id="series_serie" name="serie" maxlength="10" placeholder="F001">
                                        <span class="invalid-feedback" id="series_serie-error"></span>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>N° actual</label>
                                        <input type="number" class="form-control form-control-sm" id="series_current_number" name="current_number" min="0" value="0">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>N° siguiente <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control form-control-sm" id="series_next_number" name="next_number" min="1" value="1" readonly>
                                        <span class="invalid-feedback" id="series_next_number-error"></span>
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label>Descripción</label>
                                        <input type="text" class="form-control form-control-sm" id="series_description" name="description">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Estado</label>
                                        <select class="form-control form-control-sm" id="series_status" name="status">
                                            <option value="ACTIVE">Activo</option>
                                            <option value="INACTIVE">Inactivo</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-12 mb-0">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="series_is_default" name="is_default" value="1">
                                            <label class="custom-control-label" for="series_is_default">Serie predeterminada</label>
                                        </div>
                                    </div>
                                </div>
                                <div id="series_general_errors" class="alert alert-danger d-none mt-2 mb-0"></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>
                            Cerrar
                        </button>
                        @canany(['admin.electronic-invoice-series.store', 'admin.electronic-invoice-series.update'])
                            <button type="submit" class="btn btn-info btn-sm text-white" id="btnSaveElectronicInvoiceSeries">
                                <i class="fas fa-save mr-1"></i>
                                Guardar Serie
                            </button>
                        @endcanany
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="electronicInvoiceSeriesViewModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title font-weight-bold mb-0">Detalle de Serie</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th>Empresa</th><td id="view_series_company">-</td></tr>
                            <tr><th>Tipo</th><td id="view_series_document_type">-</td></tr>
                            <tr><th>Serie</th><td id="view_series_serie">-</td></tr>
                            <tr><th>Actual</th><td id="view_series_current_number">-</td></tr>
                            <tr><th>Siguiente</th><td id="view_series_next_number">-</td></tr>
                            <tr><th>Ambiente</th><td id="view_series_environment">-</td></tr>
                            <tr><th>Estado</th><td id="view_series_status">-</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            electronicInvoiceSeriesList: "{{ route('admin.electronic-invoice-series.list') }}",
            electronicInvoiceSeriesStore: "{{ route('admin.electronic-invoice-series.store') }}",
            electronicInvoiceSeriesBase: "{{ url('admin/electronic-invoice-series') }}"
        });
        window.electronicInvoiceSeriesCompanyEnvironments = @json($companyEnvironments);
    </script>
    @vite(['resources/js/pages/electronic-invoice-series.js'])
@endpush

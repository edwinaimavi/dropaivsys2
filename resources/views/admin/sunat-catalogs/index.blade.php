@extends('layouts.app')

@section('subtitle', 'Catalogos SUNAT')

@section('header')
    <div class="container-fluid">
        <h1 class="mb-1 font-weight-bold text-dark">
            <i class="fas fa-book text-success"></i>
            Cat&aacute;logos SUNAT
        </h1>
        <small class="text-muted">Valores base para documentos, tributos, monedas y unidades</small>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableSunatCatalogs" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th>Cat&aacute;logo</th>
                            <th>C&oacute;digo</th>
                            <th>Descripci&oacute;n</th>
                            <th>Nombre corto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        $(function () {
            $('#tableSunatCatalogs').DataTable({
                ajax: "{{ route('admin.sunat-catalogs.list') }}",
                responsive: true,
                columns: [
                    { data: 'catalog_code' },
                    { data: 'item_code' },
                    { data: 'description' },
                    { data: 'short_name' },
                    { data: 'status' }
                ],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
            });
        });
    </script>
@endpush

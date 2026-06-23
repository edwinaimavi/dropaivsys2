@extends('layouts.app')
@section('subtitle', 'Roles')
@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-user-tag"></i> Roles de Usuario
                    @can('admin.roles.store')
                         <button class="btn btn-app bg-dark" type="button" data-toggle="modal" data-target="#roleModal">
                            <i class="fas fa-plus-circle"></i>Nuevo
                        </button>
                   @endcan
                </h1>
            </div>
            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('dashboard')}}"><i class="fas fa-home"></i> Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-user-tag"></i> Roles de Usuarios</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>    
@stop

@section('content_body')

<div class="card">
  
    <div class="card-body">
        <table id="tableRole" class="table table-bordered table-hover table-sm text-center">
            <thead class="bg-gradient">
                <tr>
                    <th>#</th>
                    <th>Id</th>
                    <th>Rol</th>
                    <th>Guard Name</th>                    
                    <th></th>
                </tr>
            </thead>
        </table>
    </div>
</div>  

{{-- Modal --}}
 @include('admin.roles.partials.modal')

   
@stop

@push('css')
    
@endpush
   

@push('js')
    <script>
        window.routes = {
           storeRole: "{{ route('admin.roles.store') }}",
            rolesList: "{{ route('admin.roles.list') }}"
           /*  deleteRole: "{{ url('admin/roles') }}"  */
        };

    </script>
   @vite(['resources/js/pages/roles.js']) 
@endpush

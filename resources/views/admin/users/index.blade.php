@extends('layouts.app')

@section('subtitle', 'Usuarios')
@section('header')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-users"></i> Usuarios
                @can('admin.users.store')
                <button class="btn btn-app bg-dark" type="button" data-toggle="modal" data-target="#userModal">
                    <i class="fas fa-plus-circle"></i>Nuevo
                </button>
                @endcan
                </h1>
            </div>
            <div class="col-sm-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('home')}}"><i class="fa fa-fw fa-house-user"></i> Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-users"></i> Usuarios</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>    
@stop

@section('content_body')

<div class="card">
  
    <div class="card-body table-responsive">
        <table id="tableUser" class="table table-bordered table-hover table-sm text-center">
            <thead class="bg-gradient">
                <tr>
                    <th>#</th>
                    <th>Id</th>
                    <th>Dni</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Cel</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
        </table>
    </div>
</div>  

{{-- Modal --}}
@include('admin.users.partials.modal') 

   
@stop

@push('css')
    
@endpush

@push('js')
    <script>
        window.routes= {
            storeUser:"{{ route('admin.users.store') }}",
            usersList:"{{ route('admin.users.list') }}",
            deleteUser:"{{ url('admin/users') }}"
        }

        function previewImage(event,querySelector){
            let input = event.target;
            let imgPreview = document.querySelector(querySelector);
            if (!input.files.length) return
            let file = input.files[0];
            let objectURL = URL.createObjectURL(file);
            imgPreview.src = objectURL;
        }
    </script>
    @vite(['resources/js/pages/user.js'])
@endpush

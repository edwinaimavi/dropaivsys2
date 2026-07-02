@extends('adminlte::master')

@section('meta_tags')
    <link rel="icon" type="image/x-icon" href="{{ asset('vendor/adminlte/dist/img/logo_img1.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('vendor/adminlte/dist/img/logo_img1.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('vendor/adminlte/dist/img/logo_img.png') }}">
@stop

@section('adminlte_css')
    @yield('css')
    <style>
        .lockscreen-created-by {
            margin-top: 1rem;
            color: #64748b;
            font-size: .875rem;
            font-weight: 500;
            text-align: center;
        }

        .lockscreen-created-by a {
            color: #0f766e;
            font-weight: 700;
            text-decoration: none;
            transition: color .2s ease, opacity .2s ease;
        }

        .lockscreen-created-by a:hover {
            color: #115e59;
            opacity: .9;
            text-decoration: underline;
        }
    </style>
@stop

@section('classes_body', 'lockscreen')

@php
    $passResetUrl = View::getSection('password_reset_url') ?? config('adminlte.password_reset_url', 'password/reset');
    $dashboardUrl = View::getSection('dashboard_url') ?? config('adminlte.dashboard_url', 'home');

    if (config('adminlte.use_route_url', false)) {
        $passResetUrl = $passResetUrl ? route($passResetUrl) : '';
        $dashboardUrl = $dashboardUrl ? route($dashboardUrl) : '';
    } else {
        $passResetUrl = $passResetUrl ? url($passResetUrl) : '';
        $dashboardUrl = $dashboardUrl ? url($dashboardUrl) : '';
    }
@endphp

@section('body')
    <div class="lockscreen-wrapper">

        {{-- Lockscreen logo --}}
        <div class="lockscreen-logo">
            <a href="{{ $dashboardUrl }}">
                <img src="{{ asset(config('adminlte.logo_img')) }}" height="50">
                {!! config('adminlte.logo', '<b>Admin</b>LTE') !!}
            </a>
        </div>

        {{-- Lockscreen user name --}}
        <div class="lockscreen-name">
            {{ isset(Auth::user()->name) ? Auth::user()->name : Auth::user()->email }}
        </div>

        {{-- Lockscreen item --}}
        <div class="lockscreen-item">
            @if(config('adminlte.usermenu_image'))
                <div class="lockscreen-image">
                    <img src="{{ Auth::user()->adminlte_image() }}" alt="{{ Auth::user()->name }}">
                </div>
            @endif

            <form method="POST" action="{{ route('password.confirm') }}"
                class="lockscreen-credentials @if(! config('adminlte.usermenu_image')) ml-0 @endif">
                @csrf

                <div class="input-group">
                    <input id="password" type="password" name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="{{ __('adminlte::adminlte.password') }}" required autofocus>

                    <div class="input-group-append">
                        <button type="submit" class="btn">
                            <i class="fas fa-arrow-right text-muted"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Password error alert --}}
        @error('password')
            <div class="lockscreen-subitem text-center" role="alert">
                <b class="text-danger">{{ $message }}</b>
            </div>
        @enderror

        {{-- Help block --}}
        <div class="help-block text-center">
            {{ __('adminlte::adminlte.confirm_password_message') }}
        </div>

        {{-- Additional links --}}
        <div class="text-center">
            <a href="{{ $passResetUrl }}">
                {{ __('adminlte::adminlte.i_forgot_my_password') }}
            </a>
        </div>

        <div class="lockscreen-created-by">
            @include('partials.created-by-cico')
        </div>
    </div>
@stop

@section('adminlte_js')
    @stack('js')
    @yield('js')
@stop

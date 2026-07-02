@extends('adminlte::page')

@section('footer')
    <div class="float-right d-none d-sm-inline text-muted small">
        Version: {{ config('app.version', '1.0.0') }}
    </div>

    <span class="dp-footer-credit">
        @include('partials.created-by-cico')
    </span>
@stop

@section('css')
    <style>
        .main-footer {
            border-top: 1px solid rgba(15, 118, 110, .10);
            color: #64748b;
            font-size: .875rem;
        }

        .dp-footer-credit {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            color: #64748b;
            font-weight: 500;
        }

        .dp-footer-credit a {
            color: #0f766e;
            font-weight: 700;
            text-decoration: none;
            transition: color .2s ease, opacity .2s ease;
        }

        .dp-footer-credit a:hover {
            color: #115e59;
            opacity: .9;
            text-decoration: underline;
        }

        @media (max-width: 575.98px) {
            .main-footer {
                text-align: center;
            }
        }
    </style>
@stop

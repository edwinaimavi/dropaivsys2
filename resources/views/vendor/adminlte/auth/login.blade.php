@extends('adminlte::auth.auth-page', ['authType' => 'login'])

@section('adminlte_css_pre')
    <link rel="stylesheet" href="{{ asset('vendor/icheck-bootstrap/icheck-bootstrap.min.css') }}">
@stop

@push('css')
    <style>
        :root {
            --dp-primary: #0f766e;
            --dp-primary-dark: #115e59;
            --dp-accent: #16a34a;
            --dp-cyan: #0891b2;
            --dp-text: #0f172a;
            --dp-muted: #64748b;
            --dp-border: rgba(255, 255, 255, .45);
        }

        body.login-page {
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 15% 20%, rgba(22, 163, 74, .25), transparent 30%),
                radial-gradient(circle at 85% 15%, rgba(8, 145, 178, .25), transparent 32%),
                radial-gradient(circle at 70% 85%, rgba(15, 118, 110, .18), transparent 34%),
                linear-gradient(135deg, #eefdf8 0%, #f8fbff 48%, #e7f7ff 100%) !important;
        }

        body.login-page::before {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            content: "";
            background-image:
                linear-gradient(rgba(15, 118, 110, .055) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 118, 110, .055) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(135deg, rgba(0, 0, 0, .9), transparent 78%);
        }

        body.login-page::after {
            position: fixed;
            right: -110px;
            bottom: -130px;
            z-index: 0;
            width: 390px;
            height: 390px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(8, 145, 178, .28), rgba(22, 163, 74, .14));
            filter: blur(8px);
            content: "";
            pointer-events: none;
        }

        .login-box {
            position: relative;
            z-index: 1;
            width: 430px;
            max-width: calc(100% - 28px);
            animation: loginEnter .45s ease both;
        }

        .login-logo {
            display: none;
        }

        .login-box .card {
            border: 1px solid rgba(255, 255, 255, .58) !important;
            border-radius: 26px !important;
            background: rgba(255, 255, 255, .76) !important;
            box-shadow: 0 24px 72px rgba(15, 23, 42, .15) !important;
            overflow: hidden;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .login-box .card::before {
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, .82), rgba(255, 255, 255, .42)),
                radial-gradient(circle at top right, rgba(22, 163, 74, .16), transparent 34%);
            content: "";
        }

        .login-box .card-header {
            padding: 26px 32px 18px;
            border-bottom: 1px solid rgba(226, 232, 240, .7) !important;
            background: transparent !important;
        }

        .login-box .card-header .card-title {
            margin: 0;
        }

        .login-card-body {
            padding: 22px 32px 28px !important;
            background: transparent !important;
        }

        .login-box .card-footer {
            padding: 0 32px 28px;
            border-top: 0 !important;
            background: transparent !important;
        }

        .dropaiv-login-logo {
            display: inline-flex;
            width: 92px;
            height: 92px;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            border: 1px solid rgba(255, 255, 255, .75);
            border-radius: 24px;
            background: rgba(255, 255, 255, .72);
            box-shadow: 0 18px 38px rgba(15, 118, 110, .16);
        }

        .dropaiv-login-logo img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }

        .login-title {
            margin: 0;
            color: var(--dp-text);
            font-size: 27px;
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: 0;
        }

        .login-kicker {
            margin: 8px 0 0;
            color: var(--dp-primary);
            font-size: 14px;
            font-weight: 850;
            letter-spacing: 0;
        }

        .login-subtitle {
            max-width: 300px;
            margin: 9px auto 0;
            color: var(--dp-muted);
            font-size: 12.5px;
            font-weight: 650;
            line-height: 1.45;
        }

        .login-label {
            display: block;
            margin-bottom: 8px;
            color: #263b45;
            font-size: 12px;
            font-weight: 900;
        }

        .modern-input-group {
            margin-bottom: 17px;
        }

        .modern-input {
            height: 48px !important;
            border: 1px solid #dbe3ea !important;
            border-right: 0 !important;
            border-radius: 15px 0 0 15px !important;
            background: rgba(255, 255, 255, .9) !important;
            color: var(--dp-text) !important;
            font-size: 14px;
            font-weight: 700;
            box-shadow: none !important;
        }

        .modern-input::placeholder {
            color: #94a3b8;
            font-weight: 650;
        }

        .modern-input:focus {
            border-color: var(--dp-primary) !important;
            background: rgba(255, 255, 255, .96) !important;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, .12) !important;
        }

        .modern-input:focus + .input-group-append .input-group-text {
            border-color: var(--dp-primary) !important;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, .12) !important;
        }

        .modern-input-group .input-group-text {
            min-width: 48px;
            justify-content: center;
            border: 1px solid #dbe3ea !important;
            border-left: 0 !important;
            border-radius: 0 15px 15px 0 !important;
            color: var(--dp-primary);
            background: rgba(255, 255, 255, .9) !important;
        }

        .password-toggle {
            width: 48px;
            padding: 0;
            cursor: pointer;
            transition: color .18s ease, background .18s ease;
        }

        .password-toggle:hover,
        .password-toggle:focus {
            color: var(--dp-primary-dark);
            background: rgba(240, 253, 250, .96) !important;
            outline: none;
        }

        .modern-input-group .invalid-feedback {
            margin-top: 7px;
            color: #b42318;
            font-size: 11.5px;
            font-weight: 800;
        }

        .login-options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 1px;
            margin-bottom: 20px;
        }

        .login-options-row label {
            color: #263b45;
            font-size: 12px;
            font-weight: 850;
        }

        .icheck-primary > input:first-child:checked + input[type=hidden] + label::before,
        .icheck-primary > input:first-child:checked + label::before {
            border-color: var(--dp-primary);
            background-color: var(--dp-primary);
        }

        .btn-login-modern {
            display: inline-flex;
            width: 100%;
            height: 49px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0 !important;
            border-radius: 15px !important;
            color: #fff !important;
            background: linear-gradient(135deg, var(--dp-primary-dark), var(--dp-primary), var(--dp-accent)) !important;
            box-shadow: 0 14px 30px rgba(15, 118, 110, .28);
            font-size: 14px;
            font-weight: 900;
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }

        .btn-login-modern:hover,
        .btn-login-modern:focus {
            color: #fff !important;
            transform: translateY(-1px);
            box-shadow: 0 18px 36px rgba(15, 118, 110, .34);
            filter: saturate(1.08);
        }

        .login-footer-links {
            display: grid;
            gap: 8px;
            text-align: center;
        }

        .login-footer-links a,
        .auth-created-by a {
            color: var(--dp-primary) !important;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .login-footer-links a:hover,
        .auth-created-by a:hover {
            color: var(--dp-primary-dark) !important;
            text-decoration: underline;
        }

        .auth-created-by {
            position: relative;
            z-index: 1;
            margin-top: 18px !important;
            color: #64748b !important;
            font-size: 13px !important;
            font-weight: 650 !important;
        }

        @keyframes loginEnter {
            from {
                opacity: 0;
                transform: translateY(12px) scale(.985);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 576px) {
            body.login-page {
                overflow-y: auto;
            }

            .login-box .card-header,
            .login-card-body,
            .login-box .card-footer {
                padding-right: 22px !important;
                padding-left: 22px !important;
            }

            .login-title {
                font-size: 24px;
            }

            .dropaiv-login-logo {
                width: 78px;
                height: 78px;
                border-radius: 20px;
            }

            .dropaiv-login-logo img {
                width: 58px;
                height: 58px;
            }

            .login-options-row {
                align-items: stretch;
                flex-direction: column;
            }
        }
    </style>
@endpush

@php
    $loginUrl = View::getSection('login_url') ?? config('adminlte.login_url', 'login');
    $registerUrl = View::getSection('register_url') ?? config('adminlte.register_url', 'register');
    $passResetUrl = View::getSection('password_reset_url') ?? config('adminlte.password_reset_url', 'password/reset');

    if (config('adminlte.use_route_url', false)) {
        $loginUrl = $loginUrl ? route($loginUrl) : '';
        $registerUrl = $registerUrl ? route($registerUrl) : '';
        $passResetUrl = $passResetUrl ? route($passResetUrl) : '';
    } else {
        $loginUrl = $loginUrl ? url($loginUrl) : '';
        $registerUrl = $registerUrl ? url($registerUrl) : '';
        $passResetUrl = $passResetUrl ? url($passResetUrl) : '';
    }
@endphp

@section('auth_header')
    <div class="text-center">
        <div class="dropaiv-login-logo">
            <img src="{{ asset('vendor/adminlte/dist/img/logo_img.png') }}" alt="Logo DroPaivSys">
        </div>
        <h1 class="login-title">DroPaivSys</h1>
        <div class="login-kicker">Gesti&oacute;n de abastecimiento en salud</div>
        <p class="login-subtitle">Acceso seguro a la plataforma empresarial.</p>
    </div>
@stop

@section('auth_body')
    <form action="{{ $loginUrl }}" method="post">
        @csrf

        <label for="email" class="login-label">Email</label>
        <div class="input-group modern-input-group">
            <input id="email" type="email" name="email"
                class="form-control modern-input @error('email') is-invalid @enderror"
                value="{{ old('email') }}" placeholder="correo@dropaiv.com" autofocus autocomplete="email">

            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope {{ config('adminlte.classes_auth_icon', '') }}"></span>
                </div>
            </div>

            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <label for="password" class="login-label">Contrase&ntilde;a</label>
        <div class="input-group modern-input-group">
            <input id="password" type="password" name="password"
                class="form-control modern-input @error('password') is-invalid @enderror"
                placeholder="Ingresa tu contrase&ntilde;a" autocomplete="current-password">

            <div class="input-group-append">
                <button type="button" class="input-group-text password-toggle" id="togglePassword"
                    aria-label="Mostrar contrase&ntilde;a" aria-pressed="false">
                    <span class="fas fa-eye"></span>
                </button>
            </div>

            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="login-options-row">
            <div class="icheck-primary mb-0" title="{{ __('adminlte::adminlte.remember_me_hint') }}">
                <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember">Recordarme</label>
            </div>
        </div>

        <button type="submit" class="btn btn-login-modern">
            <span class="fas fa-sign-in-alt"></span>
            Acceder
        </button>
    </form>
@stop

@section('auth_footer')
    <div class="login-footer-links">
        @if($passResetUrl)
            <a href="{{ $passResetUrl }}">Olvid&eacute; mi contrase&ntilde;a</a>
        @endif

        @if($registerUrl)
            <a href="{{ $registerUrl }}">Solicitar acceso al sistema</a>
        @endif
    </div>
@stop

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('togglePassword');

            if (!passwordInput || !toggleButton) {
                return;
            }

            toggleButton.addEventListener('click', function () {
                const isHidden = passwordInput.getAttribute('type') === 'password';
                const icon = toggleButton.querySelector('.fas');

                passwordInput.setAttribute('type', isHidden ? 'text' : 'password');
                toggleButton.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                toggleButton.setAttribute('aria-label', isHidden ? 'Ocultar contrasena' : 'Mostrar contrasena');

                if (icon) {
                    icon.classList.toggle('fa-eye', !isHidden);
                    icon.classList.toggle('fa-eye-slash', isHidden);
                }
            });
        });
    </script>
@endpush

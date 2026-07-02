<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DroPaivSys | Gestión de abastecimiento en salud</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('vendor/adminlte/dist/img/logo_img1.ico') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('vendor/adminlte/dist/img/logo_img1.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('vendor/adminlte/dist/img/logo_img.png') }}">

    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">

    <style>
        :root {
            --primary: #0f766e;
            --primary-dark: #115e59;
            --accent: #16a34a;
            --blue: #0284c7;
            --bg: #f4f8fb;
            --text: #0f172a;
            --muted: #64748b;
            --card: rgba(255, 255, 255, .78);
            --solid-card: #ffffff;
            --border: rgba(148, 163, 184, .22);
            --soft-green: #e8f7ef;
            --soft-teal: #e6f7f6;
            --soft-blue: #e9f5fb;
            --shadow: 0 24px 70px rgba(15, 23, 42, .12);
            --nav-height: 78px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            font-family: "Segoe UI", Roboto, Arial, sans-serif;
            background:
                radial-gradient(circle at 10% 10%, rgba(22, 163, 74, .16), transparent 30%),
                radial-gradient(circle at 88% 8%, rgba(2, 132, 199, .14), transparent 28%),
                linear-gradient(135deg, #f8fcfb 0%, #edf8f5 42%, #f5faff 100%);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -3;
            background-image:
                linear-gradient(rgba(15, 118, 110, .08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 118, 110, .08) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: linear-gradient(to bottom, rgba(0, 0, 0, .58), transparent 76%);
            pointer-events: none;
        }

        a {
            color: inherit;
        }

        .landing-nav {
            position: sticky;
            top: 16px;
            z-index: 20;
            width: min(1180px, calc(100% - 32px));
            min-height: var(--nav-height);
            margin: 16px auto 0;
            padding: 12px 14px 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border: 1px solid rgba(255, 255, 255, .72);
            border-radius: 24px;
            background: rgba(255, 255, 255, .74);
            box-shadow: 0 18px 48px rgba(15, 23, 42, .08);
            backdrop-filter: blur(18px);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            text-decoration: none;
        }

        .nav-logo {
            width: clamp(132px, 17vw, 210px);
            height: auto;
            display: block;
        }

        .brand-divider {
            width: 1px;
            height: 36px;
            background: var(--border);
        }

        .brand-name {
            min-width: 0;
        }

        .brand-name strong {
            display: block;
            color: var(--text);
            font-size: 18px;
            line-height: 1.1;
        }

        .brand-name span {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            min-height: 42px;
            padding: 10px 12px;
            border-radius: 999px;
            color: #315243;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: background .2s ease, color .2s ease;
        }

        .nav-link:hover {
            color: var(--primary-dark);
            background: rgba(15, 118, 110, .08);
        }

        .login-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 46px;
            padding: 12px 18px;
            border-radius: 999px;
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            box-shadow: 0 16px 34px rgba(15, 118, 110, .26);
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 42px rgba(15, 118, 110, .32);
        }

        .page-shell {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr);
            gap: 42px;
            align-items: center;
            min-height: calc(100vh - var(--nav-height) - 34px);
            padding: 58px 0 58px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding: 9px 13px;
            border: 1px solid rgba(15, 118, 110, .18);
            border-radius: 999px;
            color: var(--primary-dark);
            background: rgba(255, 255, 255, .72);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
            font-size: 13px;
            font-weight: 900;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .eyebrow i {
            color: var(--blue);
        }

        .hero h1 {
            max-width: 760px;
            margin: 0;
            color: var(--text);
            font-size: 72px;
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: 0;
        }

        .hero h1 span {
            color: var(--primary);
        }

        .hero-lead {
            max-width: 720px;
            margin: 24px 0 0;
            color: #475569;
            font-size: 20px;
            line-height: 1.75;
        }

        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: #315243;
            background: rgba(255, 255, 255, .72);
            font-size: 13px;
            font-weight: 800;
        }

        .badge i {
            color: var(--primary);
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
            margin-top: 32px;
        }

        .secondary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            padding: 12px 18px;
            border: 1px solid rgba(15, 118, 110, .20);
            border-radius: 999px;
            color: var(--primary-dark);
            background: rgba(255, 255, 255, .72);
            box-shadow: 0 14px 34px rgba(15, 23, 42, .07);
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            transition: transform .2s ease, background .2s ease;
        }

        .secondary-button:hover {
            transform: translateY(-2px);
            background: #ffffff;
        }

        .hero-note {
            color: var(--muted);
            font-size: 14px;
            font-weight: 700;
        }

        .dashboard-visual {
            position: relative;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 32px;
            background: linear-gradient(145deg, rgba(255, 255, 255, .86), rgba(255, 255, 255, .58));
            box-shadow: var(--shadow);
            backdrop-filter: blur(22px);
        }

        .dashboard-visual::before {
            content: "";
            position: absolute;
            inset: 18px auto auto -18px;
            width: 86px;
            height: 86px;
            border-radius: 26px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            opacity: .14;
            transform: rotate(14deg);
        }

        .dashboard-top {
            position: relative;
            padding: 22px;
            border-radius: 24px;
            color: #ffffff;
            background:
                linear-gradient(135deg, rgba(15, 23, 42, .98), rgba(15, 118, 110, .92)),
                radial-gradient(circle at top right, rgba(255, 255, 255, .18), transparent 36%);
            overflow: hidden;
        }

        .dashboard-top::after {
            content: "";
            position: absolute;
            right: -42px;
            top: -48px;
            width: 150px;
            height: 150px;
            border: 24px solid rgba(255, 255, 255, .08);
            border-radius: 50%;
        }

        .dashboard-title {
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .dashboard-title h2 {
            margin: 0 0 8px;
            font-size: 24px;
            line-height: 1.15;
        }

        .dashboard-title p {
            max-width: 300px;
            margin: 0;
            color: rgba(255, 255, 255, .78);
            font-size: 14px;
            line-height: 1.55;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            color: #dffcf0;
            background: rgba(255, 255, 255, .12);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #7ee2a8;
            box-shadow: 0 0 0 5px rgba(126, 226, 168, .14);
        }

        .date-strip {
            position: relative;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center;
            margin-top: 22px;
            padding: 14px;
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 18px;
            background: rgba(255, 255, 255, .10);
        }

        .date-label {
            margin: 0 0 4px;
            color: rgba(255, 255, 255, .62);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .current-date {
            margin: 0;
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.35;
        }

        .current-time {
            min-width: 84px;
            padding: 11px 12px;
            border-radius: 14px;
            color: var(--primary-dark);
            background: #ffffff;
            text-align: center;
            font-size: 22px;
            font-weight: 900;
            line-height: 1;
        }

        .process-list {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }

        .process-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .05);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .process-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, .08);
        }

        .process-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            color: var(--primary-dark);
            background: var(--soft-teal);
        }

        .process-item:nth-child(2) .process-icon {
            color: #166534;
            background: var(--soft-green);
        }

        .process-item:nth-child(3) .process-icon {
            color: #075985;
            background: var(--soft-blue);
        }

        .process-item:nth-child(4) .process-icon {
            color: #365314;
            background: #eef8dc;
        }

        .process-copy strong {
            display: block;
            color: var(--text);
            font-size: 14px;
            line-height: 1.25;
        }

        .process-copy span {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
        }

        .process-state {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .section {
            padding: 42px 0;
        }

        .section-header {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 22px;
        }

        .section-kicker {
            margin: 0 0 9px;
            color: var(--primary);
            font-size: 13px;
            font-weight: 900;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .section-title {
            max-width: 720px;
            margin: 0;
            color: var(--text);
            font-size: 42px;
            line-height: 1.08;
            font-weight: 900;
        }

        .section-text {
            max-width: 420px;
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .module-card {
            position: relative;
            min-height: 210px;
            padding: 22px;
            border: 1px solid rgba(255, 255, 255, .74);
            border-radius: 24px;
            background: rgba(255, 255, 255, .74);
            box-shadow: 0 16px 42px rgba(15, 23, 42, .07);
            backdrop-filter: blur(16px);
            overflow: hidden;
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }

        .module-card::after {
            content: "";
            position: absolute;
            right: -34px;
            top: -36px;
            width: 96px;
            height: 96px;
            border-radius: 30px;
            background: rgba(15, 118, 110, .08);
            transform: rotate(18deg);
        }

        .module-card:hover {
            transform: translateY(-5px);
            border-color: rgba(15, 118, 110, .22);
            box-shadow: 0 24px 56px rgba(15, 23, 42, .10);
        }

        .module-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            margin-bottom: 18px;
            border-radius: 16px;
            color: var(--primary-dark);
            background: linear-gradient(135deg, var(--soft-teal), #ffffff);
            font-size: 20px;
        }

        .module-card h3 {
            position: relative;
            margin: 0 0 10px;
            color: var(--text);
            font-size: 19px;
            line-height: 1.25;
        }

        .module-card p {
            position: relative;
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.65;
        }

        .institutional-band {
            position: relative;
            margin: 38px 0 54px;
            padding: 34px;
            border-radius: 30px;
            color: #ffffff;
            background:
                linear-gradient(135deg, rgba(15, 23, 42, .97), rgba(17, 94, 89, .94)),
                radial-gradient(circle at 82% 14%, rgba(255, 255, 255, .18), transparent 30%);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .institutional-band::before {
            content: "";
            position: absolute;
            right: -90px;
            bottom: -110px;
            width: 280px;
            height: 280px;
            border: 38px solid rgba(255, 255, 255, .07);
            border-radius: 50%;
        }

        .institutional-content {
            position: relative;
            display: grid;
            grid-template-columns: 1fr .92fr;
            gap: 36px;
            align-items: center;
        }

        .institutional-band h2 {
            max-width: 620px;
            margin: 0;
            font-size: 44px;
            line-height: 1.08;
            font-weight: 900;
        }

        .institutional-band .section-kicker {
            color: #9be0bd;
        }

        .institutional-band p {
            margin: 18px 0 0;
            color: rgba(255, 255, 255, .78);
            font-size: 16px;
            line-height: 1.75;
        }

        .trust-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .trust-item {
            min-height: 92px;
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 18px;
            background: rgba(255, 255, 255, .08);
        }

        .trust-item i {
            margin-bottom: 10px;
            color: #9be0bd;
            font-size: 18px;
        }

        .trust-item strong {
            display: block;
            font-size: 14px;
            line-height: 1.3;
        }

        .trust-item span {
            display: block;
            margin-top: 5px;
            color: rgba(255, 255, 255, .68);
            font-size: 12px;
            line-height: 1.45;
        }

        .landing-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0 34px;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
        }

        .landing-footer a {
            color: var(--primary);
            font-weight: 800;
            text-decoration: none;
            transition: color .2s ease, opacity .2s ease;
        }

        .landing-footer a:hover {
            color: var(--primary-dark);
            opacity: .9;
            text-decoration: underline;
        }

        @media (max-width: 1060px) {
            .nav-link {
                display: none;
            }

            .hero {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .dashboard-visual {
                max-width: 720px;
            }

            .hero h1 {
                font-size: 58px;
            }

            .section-title,
            .institutional-band h2 {
                font-size: 38px;
            }

            .section-header,
            .institutional-content {
                grid-template-columns: 1fr;
                display: grid;
            }
        }

        @media (max-width: 860px) {
            .module-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            :root {
                --nav-height: 68px;
            }

            .landing-nav {
                top: 10px;
                width: min(100% - 20px, 1180px);
                min-height: var(--nav-height);
                padding: 10px;
                border-radius: 20px;
                gap: 10px;
            }

            .nav-brand {
                gap: 10px;
            }

            .nav-logo {
                width: 122px;
            }

            .brand-divider,
            .brand-name span {
                display: none;
            }

            .brand-name strong {
                font-size: 14px;
            }

            .login-button {
                min-height: 42px;
                padding: 10px 12px;
                font-size: 13px;
            }

            .login-button i {
                display: none;
            }

            .page-shell {
                width: min(100% - 24px, 1180px);
            }

            .hero {
                padding: 38px 0 34px;
                gap: 28px;
            }

            .hero h1 {
                font-size: 42px;
            }

            .hero-lead {
                font-size: 17px;
            }

            .section-title,
            .institutional-band h2 {
                font-size: 30px;
            }

            .hero-actions,
            .secondary-button {
                width: 100%;
            }

            .hero-note {
                width: 100%;
            }

            .dashboard-visual {
                padding: 12px;
                border-radius: 24px;
            }

            .dashboard-top {
                padding: 18px;
                border-radius: 20px;
            }

            .dashboard-title,
            .date-strip {
                grid-template-columns: 1fr;
                flex-direction: column;
            }

            .current-time {
                width: 100%;
            }

            .process-item {
                grid-template-columns: auto 1fr;
            }

            .process-state {
                grid-column: 2;
            }

            .section {
                padding: 30px 0;
            }

            .section-header {
                gap: 12px;
            }

            .module-grid,
            .trust-grid {
                grid-template-columns: 1fr;
            }

            .institutional-band {
                margin-bottom: 34px;
                padding: 24px;
                border-radius: 24px;
            }
        }
    </style>
</head>

<body>
    <nav class="landing-nav" aria-label="Navegación principal">
        <a class="nav-brand" href="{{ url('/') }}" aria-label="DroPaivSys">
            <img class="nav-logo" src="{{ asset('vendor/adminlte/dist/img/logo_img.png') }}" alt="DroPaiv Salud & Bienestar">
            <span class="brand-divider"></span>
            <span class="brand-name">
                <strong>DroPaivSys</strong>
                <span>Abastecimiento de salud</span>
            </span>
        </a>

        <div class="nav-actions">
            <a class="nav-link" href="#modulos">Módulos</a>
            <a class="nav-link" href="#institucional">Institucional</a>

            @auth
                <a href="/admin" class="login-button">
                    <i class="fas fa-arrow-right"></i>
                    Ingresar al Sistema
                </a>
            @else
                <a href="{{ route('login') }}" class="login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    Iniciar Sesión
                </a>
            @endauth
        </div>
    </nav>

    <main class="page-shell">
        <section class="hero" aria-label="Portada de DroPaivSys">
            <div class="hero-copy">
                <div id="greeting" class="eyebrow">
                    <i class="fas fa-sun"></i>
                    Buenos días
                </div>

                <h1>
                    Gestión inteligente para el <span>abastecimiento de salud</span>
                </h1>

                <p class="hero-lead">
                    Plataforma integral para administrar estudios de mercado, cotizaciones, órdenes de compra,
                    ingresos de almacén, Kardex y trazabilidad documental de Droguería Dropaiv.
                </p>

                <div class="badge-row" aria-label="Identidad institucional">
                    <span class="badge"><i class="fas fa-shield-alt"></i> Proveedor del Estado</span>
                    <span class="badge"><i class="fas fa-capsules"></i> Medicamentos</span>
                    <span class="badge"><i class="fas fa-stethoscope"></i> Dispositivos médicos</span>
                    <span class="badge"><i class="fas fa-truck-loading"></i> Gestión logística</span>
                </div>

                <div class="hero-actions">
                    <a href="#modulos" class="secondary-button">
                        <i class="fas fa-layer-group"></i>
                        Conoce la plataforma
                    </a>
                    <span class="hero-note">Control comercial, documental y logístico en una sola plataforma.</span>
                </div>
            </div>

            <aside class="dashboard-visual" aria-label="Vista resumida de control operativo">
                <div class="dashboard-top">
                    <div class="dashboard-title">
                        <div>
                            <h2>Control operativo</h2>
                            <p>Seguimiento de procesos clave para atención a entidades públicas y privadas.</p>
                        </div>

                        <div class="status-pill">
                            <span class="status-dot"></span>
                            Plataforma activa
                        </div>
                    </div>

                    <div class="date-strip">
                        <div>
                            <p class="date-label">Fecha actual</p>
                            <p id="currentDate" class="current-date">Cargando fecha...</p>
                        </div>
                        <div id="currentTime" class="current-time">00:00</div>
                    </div>
                </div>

                <div class="process-list" aria-label="Procesos principales">
                    <div class="process-item">
                        <span class="process-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                        <span class="process-copy">
                            <strong>Cotizaciones</strong>
                            <span>Propuestas comerciales documentadas.</span>
                        </span>
                        <span class="process-state"><i class="fas fa-circle"></i> Gestión</span>
                    </div>

                    <div class="process-item">
                        <span class="process-icon"><i class="fas fa-file-signature"></i></span>
                        <span class="process-copy">
                            <strong>Órdenes de Compra</strong>
                            <span>Control de pedidos y compras a proveedores.</span>
                        </span>
                        <span class="process-state"><i class="fas fa-circle"></i> Control</span>
                    </div>

                    <div class="process-item">
                        <span class="process-icon"><i class="fas fa-warehouse"></i></span>
                        <span class="process-copy">
                            <strong>Almacén</strong>
                            <span>Ingresos físicos y documentos asociados.</span>
                        </span>
                        <span class="process-state"><i class="fas fa-circle"></i> Registro</span>
                    </div>

                    <div class="process-item">
                        <span class="process-icon"><i class="fas fa-boxes"></i></span>
                        <span class="process-copy">
                            <strong>Kardex</strong>
                            <span>Movimientos, saldos y valorización.</span>
                        </span>
                        <span class="process-state"><i class="fas fa-circle"></i> Trazable</span>
                    </div>
                </div>
            </aside>
        </section>

        <section id="modulos" class="section" aria-label="Módulos principales">
            <div class="section-header">
                <div>
                    <p class="section-kicker">Módulos principales</p>
                    <h2 class="section-title">Una operación comercial y logística mejor organizada.</h2>
                </div>
                <p class="section-text">
                    DroPaivSys centraliza información crítica para reducir dispersión documental,
                    mantener trazabilidad y sostener procesos comerciales más ordenados.
                </p>
            </div>

            <div class="module-grid">
                <article class="module-card">
                    <div class="module-icon"><i class="fas fa-search-dollar"></i></div>
                    <h3>Estudios de Mercado</h3>
                    <p>Centraliza requerimientos, proveedores, comparativos y adjudicaciones para decisiones comerciales mejor sustentadas.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <h3>Cotizaciones</h3>
                    <p>Genera propuestas claras, ordenadas y vinculadas con el flujo comercial de la droguería.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon"><i class="fas fa-file-signature"></i></div>
                    <h3>Órdenes de Compra</h3>
                    <p>Controla compras de clientes y proveedores, manteniendo documentos y estados bajo seguimiento.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon"><i class="fas fa-dolly-flatbed"></i></div>
                    <h3>Ingresos de Almacén</h3>
                    <p>Registra ingresos físicos, sustento documental y movimientos relacionados al abastecimiento.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon"><i class="fas fa-boxes"></i></div>
                    <h3>Kardex</h3>
                    <p>Consulta movimientos, saldos y valorización para una lectura precisa del inventario disponible.</p>
                </article>

                <article class="module-card">
                    <div class="module-icon"><i class="fas fa-folder-open"></i></div>
                    <h3>Trazabilidad documental</h3>
                    <p>Organiza archivos y respaldos por proceso para sostener una gestión institucional auditable.</p>
                </article>
            </div>
        </section>

        <section id="institucional" class="institutional-band" aria-label="Bloque institucional">
            <div class="institutional-content">
                <div>
                    <p class="section-kicker">Droguería Dropaiv</p>
                    <h2>Abastecimiento seguro y ordenado para el sector salud</h2>
                    <p>
                        El sistema ayuda a controlar procesos comerciales, documentales y logísticos
                        para la atención a entidades públicas y privadas que requieren medicamentos,
                        dispositivos médicos y productos para la salud.
                    </p>
                </div>

                <div class="trust-grid">
                    <div class="trust-item">
                        <i class="fas fa-clipboard-check"></i>
                        <strong>Gestión documental</strong>
                        <span>Sustentos y archivos comerciales centralizados.</span>
                    </div>

                    <div class="trust-item">
                        <i class="fas fa-route"></i>
                        <strong>Control de trazabilidad</strong>
                        <span>Seguimiento desde cotización hasta almacén.</span>
                    </div>

                    <div class="trust-item">
                        <i class="fas fa-chart-line"></i>
                        <strong>Inventario valorizado</strong>
                        <span>Movimientos, saldos y valorización consultable.</span>
                    </div>

                    <div class="trust-item">
                        <i class="fas fa-hand-holding-medical"></i>
                        <strong>Abastecimiento oportuno</strong>
                        <span>Procesos alineados al sector salud.</span>
                    </div>
                </div>
            </div>
        </section>

        <footer class="landing-footer">
            @include('partials.created-by-cico')
        </footer>
    </main>

    <script>
        function updateWelcomePanel() {
            const now = new Date();
            const hour = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentTime = document.getElementById('currentTime');
            const currentDate = document.getElementById('currentDate');
            const greeting = document.getElementById('greeting');

            let text = 'Buenos días';
            let icon = 'fa-sun';

            if (hour >= 12 && hour < 18) {
                text = 'Buenas tardes';
                icon = 'fa-cloud-sun';
            } else if (hour >= 18) {
                text = 'Buenas noches';
                icon = 'fa-moon';
            }

            currentTime.textContent = String(hour).padStart(2, '0') + ':' + minutes;
            currentDate.textContent = now.toLocaleDateString('es-PE', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
            greeting.innerHTML = '<i class="fas ' + icon + '"></i>' + text;
        }

        updateWelcomePanel();
        setInterval(updateWelcomePanel, 1000);
    </script>
</body>

</html>

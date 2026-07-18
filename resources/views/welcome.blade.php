<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="DropaivSys, plataforma integral para la gestión del abastecimiento en salud.">
    <title>DropaivSys | Abastecimiento inteligente en salud</title>
    <link rel="icon" href="{{ asset('vendor/adminlte/dist/img/logo_img1.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    @vite('resources/css/welcome.css')
</head>
<body class="landing-page">
    @php
        $systemUrl = auth()->check() ? url('/admin') : route('login');
    @endphp

    <header class="site-header">
        <nav class="public-nav container" aria-label="Navegación principal">
            <a class="brand" href="{{ route('welcome') }}" aria-label="DropaivSys, página principal">
                <img src="{{ asset('vendor/adminlte/dist/img/logo_img.png') }}" alt="DROPAIV Salud y Bienestar">
                <span class="brand-copy">
                    <strong>DropaivSys</strong>
                    <small>Plataforma integral de abastecimiento en salud</small>
                </span>
            </a>

            <div class="nav-links" aria-label="Secciones de la página">
                <a href="#soluciones">Soluciones</a>
                <a href="#modulos">Módulos</a>
                <a href="#beneficios">Beneficios</a>
                <a href="#integraciones">Integraciones</a>
                <a href="#nosotros">Nosotros</a>
            </div>

            <a class="button button-primary nav-login" href="{{ $systemUrl }}">
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                Ingresar al Sistema
            </a>
        </nav>
    </header>

    <main>
        <section id="soluciones" class="hero container">
            <div class="hero-content">
                <span class="eyebrow"><i class="fas fa-heartbeat"></i> Tecnología para el abastecimiento en salud</span>
                <h1>Abastecimiento inteligente para una <span>salud que avanza</span></h1>
                <p class="hero-lead">DropaivSys optimiza todo el ciclo de abastecimiento en salud: cotizaciones, órdenes de compra, almacén, Kardex, trazabilidad documental y control operativo.</p>

                <div class="hero-actions">
                    <a class="button button-primary" href="#modulos"><i class="fas fa-layer-group"></i> Conocer la plataforma</a>
                    <a class="button button-secondary" href="{{ $systemUrl }}"><i class="fas fa-sign-in-alt"></i> Ingresar al Sistema</a>
                </div>

                <div class="quick-benefits" aria-label="Beneficios principales">
                    <span><i class="fas fa-satellite-dish"></i> Control en tiempo real</span>
                    <span><i class="fas fa-check-double"></i> Procesos estandarizados</span>
                    <span><i class="fas fa-route"></i> Trazabilidad documentaria</span>
                    <span><i class="fas fa-chart-line"></i> Decisiones basadas en datos</span>
                </div>
            </div>

            <div class="dashboard-shell" aria-label="Vista conceptual del panel operativo">
                <div class="dashboard-toolbar">
                    <div class="dashboard-brand"><span><i class="fas fa-chart-pie"></i></span><div><strong>Resumen operativo</strong><small>Visión general del abastecimiento</small></div></div>
                    <span class="live-status"><i></i> Operación activa</span>
                </div>

                <div class="metric-grid">
                    <article class="metric-card"><span class="metric-icon mint"><i class="fas fa-file-invoice-dollar"></i></span><div><small>Cotizaciones activas</small><strong>24</strong><em><i class="fas fa-arrow-up"></i> 8% este mes</em></div></article>
                    <article class="metric-card"><span class="metric-icon blue"><i class="fas fa-shopping-cart"></i></span><div><small>Órdenes de compra</small><strong>16</strong><em>5 en proceso</em></div></article>
                    <article class="metric-card"><span class="metric-icon amber"><i class="fas fa-warehouse"></i></span><div><small>Ingresos de almacén</small><strong>12</strong><em>Últimos 30 días</em></div></article>
                    <article class="metric-card"><span class="metric-icon navy"><i class="fas fa-boxes"></i></span><div><small>Valor inventario</small><strong>S/ 248K</strong><em>Stock valorizado</em></div></article>
                </div>

                <div class="dashboard-lower">
                    <article class="activity-card">
                        <div class="card-heading"><div><strong>Flujo de abastecimiento</strong><small>Actividad de los últimos 7 días</small></div><span>Esta semana</span></div>
                        <div class="chart" aria-hidden="true"><span style="height:36%"></span><span style="height:58%"></span><span style="height:46%"></span><span style="height:76%"></span><span style="height:64%"></span><span style="height:90%"></span><span style="height:72%"></span></div>
                        <div class="chart-days"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
                    </article>
                    <div class="status-list">
                        <article><span class="status-icon"><i class="fas fa-book"></i></span><div><strong>Kardex actualizado</strong><small>Movimientos trazables</small></div><i class="fas fa-check-circle ok"></i></article>
                        <article><span class="status-icon"><i class="fas fa-link"></i></span><div><strong>Trazabilidad</strong><small>Documentos vinculados</small></div><i class="fas fa-check-circle ok"></i></article>
                        <article class="alert-row"><span class="status-icon"><i class="fas fa-bell"></i></span><div><strong>Alertas importantes</strong><small>3 lotes por revisar</small></div><b>3</b></article>
                    </div>
                </div>
            </div>
        </section>

        <section class="trust-strip" aria-label="Sectores atendidos">
            <div class="container trust-content"><span>Diseñado para operaciones de salud</span><div><span><i class="fas fa-building"></i> Entidades públicas</span><span><i class="fas fa-capsules"></i> Droguerías</span><span><i class="fas fa-clinic-medical"></i> Clínicas</span><span><i class="fas fa-network-wired"></i> Redes de salud</span></div></div>
        </section>

        <section id="modulos" class="section container">
            <div class="section-heading centered"><span class="section-label">Ecosistema integrado</span><h2>Una plataforma, todos los procesos</h2><p>Información conectada de principio a fin para operar con orden, velocidad y trazabilidad.</p></div>
            <div class="module-grid">
                <article class="module-card"><span><i class="fas fa-file-invoice-dollar"></i></span><h3>Cotizaciones</h3><p>Propuestas comerciales claras, comparables y vinculadas con cada oportunidad.</p></article>
                <article class="module-card"><span><i class="fas fa-file-signature"></i></span><h3>Órdenes de Compra</h3><p>Seguimiento coordinado de pedidos de clientes y compras a proveedores.</p></article>
                <article class="module-card"><span><i class="fas fa-warehouse"></i></span><h3>Almacén</h3><p>Ingresos controlados con lotes, vencimientos y sustento documental.</p></article>
                <article class="module-card"><span><i class="fas fa-boxes"></i></span><h3>Kardex</h3><p>Movimientos, saldos y valorización del inventario con lectura inmediata.</p></article>
                <article class="module-card"><span><i class="fas fa-route"></i></span><h3>Trazabilidad</h3><p>Seguimiento de cada operación desde el requerimiento hasta su atención.</p></article>
                <article class="module-card"><span><i class="fas fa-folder-open"></i></span><h3>Gestión Documental</h3><p>Archivos y respaldos centralizados para una operación auditable.</p></article>
                <article class="module-card featured"><span><i class="fas fa-file-invoice"></i></span><h3>Facturación Electrónica</h3><p>Comprobantes y series integrados al flujo comercial de la organización.</p><em>Integración operativa</em></article>
            </div>
        </section>

        <section id="beneficios" class="section benefit-section">
            <div class="container benefit-layout">
                <div class="benefit-copy"><span class="section-label">Control que genera confianza</span><h2>Más claridad en cada decisión operativa</h2><p>DropaivSys convierte procesos dispersos en un flujo conectado, medible y preparado para crecer.</p><div class="impact-card"><i class="fas fa-shield-alt"></i><div><strong>Operación más segura</strong><span>Información centralizada y disponible para el equipo autorizado.</span></div></div></div>
                <div class="benefit-list">
                    <article><i class="fas fa-check"></i><div><strong>Reduce errores operativos</strong><span>Estandariza registros y disminuye reprocesos.</span></div></article>
                    <article><i class="fas fa-check"></i><div><strong>Centraliza documentos</strong><span>Conserva respaldos asociados a cada proceso.</span></div></article>
                    <article><i class="fas fa-check"></i><div><strong>Controla lotes y vencimientos</strong><span>Mejora la visibilidad del inventario sensible.</span></div></article>
                    <article><i class="fas fa-check"></i><div><strong>Mejora el seguimiento de compras</strong><span>Conoce estados y pendientes con rapidez.</span></div></article>
                    <article><i class="fas fa-check"></i><div><strong>Facilita auditoría y trazabilidad</strong><span>Consulta el historial operativo relacionado.</span></div></article>
                    <article><i class="fas fa-check"></i><div><strong>Prepara futuras integraciones</strong><span>Una base ordenada para evolucionar digitalmente.</span></div></article>
                </div>
            </div>
        </section>

        <section id="integraciones" class="section container integration-section">
            <div class="integration-panel"><div><span class="section-label light">Procesos conectados</span><h2>Una sola fuente de información para toda la operación</h2><p>Clientes, proveedores, artículos, documentos, compras, almacén y facturación comparten un flujo coherente.</p></div><div class="integration-map" aria-hidden="true"><span><i class="fas fa-users"></i></span><span><i class="fas fa-handshake"></i></span><b><i class="fas fa-heartbeat"></i></b><span><i class="fas fa-warehouse"></i></span><span><i class="fas fa-file-invoice"></i></span></div></div>
        </section>

        <section id="nosotros" class="section container final-cta">
            <span class="cta-orb"><i class="fas fa-rocket"></i></span><div><span class="section-label light">Impulse su operación</span><h2>Transforme su gestión de abastecimiento hoy</h2><p>Menos quiebres, más eficiencia y mayor control para una mejor atención en salud.</p></div><a class="button button-light" href="{{ $systemUrl }}">Ingresar al Sistema <i class="fas fa-arrow-right"></i></a>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content"><div class="footer-brand"><img src="{{ asset('vendor/adminlte/dist/img/logo_img.png') }}" alt="DROPAIV Salud y Bienestar"><div><strong>DropaivSys</strong><span>Plataforma integral de abastecimiento en salud</span></div></div><div class="footer-meta"><span>&copy; {{ date('Y') }} DROPAIV Salud &amp; Bienestar</span>@include('partials.created-by-cico')</div></div>
    </footer>
</body>
</html>

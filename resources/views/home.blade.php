@extends('layouts.app')

@section('subtitle', 'Dashboard')

@section('header')
    <div class="container-fluid">
        <div class="dp-dashboard-hero">
            <div class="dp-hero-copy">
                <div class="dp-hero-kicker">
                    <i class="fas fa-shield-alt"></i>
                    Panel operativo de abastecimiento
                </div>

                <h1>{{ $greeting }}, {{ $userName }}</h1>
                <p>
                    Bienvenido a DroPaivSys, tu panel de control para gestionar cotizaciones,
                    compras, almacén, Kardex y trazabilidad documental.
                </p>

                <div class="dp-hero-badges">
                    <span><i class="fas fa-landmark"></i> Proveedor del Estado</span>
                    <span><i class="fas fa-heartbeat"></i> Salud & Bienestar</span>
                    <span><i class="fas fa-truck-loading"></i> Gestión logística</span>
                    <span><i class="far fa-calendar-alt"></i> {{ ucfirst($todayLabel) }}</span>
                </div>
            </div>

            <aside class="dp-hero-chart-card" aria-label="Resumen estadístico del mes">
                <div class="dp-hero-chart-head">
                    <span>Resumen mensual</span>
                    <strong>{{ $dashboardYear }}</strong>
                </div>
                <div class="dp-hero-bars" aria-hidden="true">
                    <span style="--bar-height: {{ min(100, max(12, $metrics['monthQuotes'] * 12)) }}%"></span>
                    <span style="--bar-height: {{ min(100, max(12, $metrics['monthCustomerPurchaseOrders'] * 12)) }}%"></span>
                    <span style="--bar-height: {{ min(100, max(12, $metrics['monthSupplierPurchaseOrders'] * 12)) }}%"></span>
                    <span style="--bar-height: {{ min(100, max(12, $metrics['monthWarehouseEntries'] * 12)) }}%"></span>
                </div>
                <div class="dp-hero-chart-legend">
                    <span>Cotizaciones</span>
                    <span>Órdenes</span>
                    <span>Compras</span>
                    <span>Almacén</span>
                </div>
            </aside>
        </div>
    </div>
@stop

@section('content_body')
    @php
        $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);

        $metricCards = [
            [
                'permission' => 'admin.quotes.index',
                'class' => 'metric-teal',
                'icon' => 'fas fa-file-invoice-dollar',
                'label' => 'Cotizaciones emitidas',
                'value' => number_format($metrics['totalQuotes']),
                'caption' => 'Documentos comerciales generados.',
                'pill' => number_format($metrics['monthQuotes']) . ' este mes',
            ],
            [
                'permission' => 'admin.customer-purchase-orders.index',
                'class' => 'metric-green',
                'icon' => 'fas fa-file-signature',
                'label' => 'Órdenes de clientes',
                'value' => number_format($metrics['totalCustomerPurchaseOrders']),
                'caption' => 'Pedidos recibidos y documentados.',
                'pill' => number_format($metrics['monthCustomerPurchaseOrders']) . ' este mes',
            ],
            [
                'permission' => 'admin.supplier-purchase-orders.index',
                'class' => 'metric-emerald',
                'icon' => 'fas fa-truck-loading',
                'label' => 'Órdenes a proveedores',
                'value' => number_format($metrics['totalSupplierPurchaseOrders']),
                'caption' => 'Compras para abastecimiento.',
                'pill' => number_format($metrics['monthSupplierPurchaseOrders']) . ' este mes',
            ],
            [
                'permission' => 'admin.warehouse-entries.index',
                'class' => 'metric-cyan',
                'icon' => 'fas fa-warehouse',
                'label' => 'Ingresos de almacén',
                'value' => number_format($metrics['totalWarehouseEntries']),
                'caption' => 'Recepciones físicas registradas.',
                'pill' => number_format($metrics['monthWarehouseEntries']) . ' este mes',
            ],
            [
                'permission' => 'admin.kardex.index',
                'class' => 'metric-warning',
                'icon' => 'fas fa-boxes',
                'label' => 'Artículos con stock',
                'value' => number_format($metrics['articlesWithStock']),
                'caption' => 'Ítems disponibles en inventario.',
                'pill' => 'Stock actual',
            ],
            [
                'permission' => 'admin.kardex.index',
                'class' => 'metric-dark',
                'icon' => 'fas fa-chart-line',
                'label' => 'Valor inventario',
                'value' => $money($metrics['inventoryValue']),
                'caption' => 'Valorización actual del stock.',
                'pill' => 'Kardex valorizado',
            ],
        ];

        $quickLinks = [
            [
                'permission' => 'admin.quotes.store',
                'route' => Route::has('admin.quotes.index') ? route('admin.quotes.index') : null,
                'icon' => 'fas fa-plus-circle',
                'title' => 'Nueva Cotización',
                'description' => 'Crear propuesta comercial para cliente.',
            ],
            [
                'permission' => 'admin.market-studies.store',
                'route' => Route::has('admin.market-studies.index') ? route('admin.market-studies.index') : null,
                'icon' => 'fas fa-search-dollar',
                'title' => 'Nuevo Estudio de Mercado',
                'description' => 'Centralizar requerimientos y proveedores.',
            ],
            [
                'permission' => 'admin.customer-purchase-orders.store',
                'route' => Route::has('admin.customer-purchase-orders.index') ? route('admin.customer-purchase-orders.index') : null,
                'icon' => 'fas fa-file-contract',
                'title' => 'Orden Compra Cliente',
                'description' => 'Registrar pedido recibido.',
            ],
            [
                'permission' => 'admin.supplier-purchase-orders.store',
                'route' => Route::has('admin.supplier-purchase-orders.index') ? route('admin.supplier-purchase-orders.index') : null,
                'icon' => 'fas fa-shipping-fast',
                'title' => 'Orden Compra Proveedor',
                'description' => 'Gestionar compra de abastecimiento.',
            ],
            [
                'permission' => 'admin.warehouse-entries.store',
                'route' => Route::has('admin.warehouse-entries.index') ? route('admin.warehouse-entries.index') : null,
                'icon' => 'fas fa-dolly-flatbed',
                'title' => 'Nuevo Ingreso de Almacén',
                'description' => 'Registrar mercadería recibida.',
            ],
            [
                'permission' => 'admin.kardex.index',
                'route' => Route::has('admin.kardex.index') ? route('admin.kardex.index') : null,
                'icon' => 'fas fa-layer-group',
                'title' => 'Ver Kardex',
                'description' => 'Consultar movimientos y stock valorizado.',
            ],
        ];

        $operationSections = [
            [
                'permission' => 'admin.quotes.index',
                'title' => 'Cotizaciones recientes',
                'icon' => 'fas fa-file-invoice-dollar',
                'route' => Route::has('admin.quotes.index') ? route('admin.quotes.index') : '#',
                'items' => $latestQuotes,
                'empty' => 'No hay cotizaciones recientes.',
                'label' => fn ($item) => $item->quote_number ?? 'Cotización #' . $item->id,
                'detail' => fn ($item) => optional($item->customer)->business_name
                    ?? optional($item->customer)->full_name
                    ?? 'Cliente no registrado',
                'meta' => fn ($item) => $money($item->grand_total ?? 0),
            ],
            [
                'permission' => 'admin.supplier-purchase-orders.index',
                'title' => 'Órdenes de compra',
                'icon' => 'fas fa-truck-loading',
                'route' => Route::has('admin.supplier-purchase-orders.index') ? route('admin.supplier-purchase-orders.index') : '#',
                'items' => $latestSupplierOrders,
                'empty' => 'No hay órdenes de compra recientes.',
                'label' => fn ($item) => $item->code ?? 'Orden #' . $item->id,
                'detail' => fn ($item) => optional($item->supplier)->business_name
                    ?? optional($item->supplier)->name
                    ?? 'Proveedor no registrado',
                'meta' => fn ($item) => $money($item->grand_total ?? 0),
            ],
            [
                'permission' => 'admin.warehouse-entries.index',
                'title' => 'Ingresos de almacén',
                'icon' => 'fas fa-warehouse',
                'route' => Route::has('admin.warehouse-entries.index') ? route('admin.warehouse-entries.index') : '#',
                'items' => $latestWarehouseEntries,
                'empty' => 'No hay ingresos recientes.',
                'label' => fn ($item) => $item->entry_number ?? 'Ingreso #' . $item->id,
                'detail' => fn ($item) => optional($item->supplier)->business_name
                    ?? optional($item->warehouse)->name
                    ?? 'Proveedor no registrado',
                'meta' => fn ($item) => $money($item->grand_total ?? 0),
            ],
            [
                'permission' => 'admin.kardex.index',
                'title' => 'Movimientos Kardex',
                'icon' => 'fas fa-clipboard-list',
                'route' => Route::has('admin.kardex.index') ? route('admin.kardex.index') : '#',
                'items' => $latestKardexMovements,
                'empty' => 'No hay movimientos Kardex recientes.',
                'label' => fn ($item) => $item->movement_number ?? 'Movimiento #' . $item->id,
                'detail' => fn ($item) => optional($item->article)->name
                    ?? optional($item->warehouse)->name
                    ?? 'Artículo no registrado',
                'meta' => fn ($item) => ($item->quantity_in > 0 ? '+' . number_format((float) $item->quantity_in, 2) : '-' . number_format((float) $item->quantity_out, 2)),
            ],
        ];
    @endphp

    <div class="dp-dashboard">
        <section class="dp-metric-grid" aria-label="Métricas principales">
            @foreach ($metricCards as $card)
                @can($card['permission'])
                    <article class="dp-metric-card {{ $card['class'] }}">
                        <div class="dp-metric-top">
                            <span class="dp-metric-icon"><i class="{{ $card['icon'] }}"></i></span>
                            <span class="dp-metric-pill">{{ $card['pill'] }}</span>
                        </div>
                        <small>{{ $card['label'] }}</small>
                        <strong>{{ $card['value'] }}</strong>
                        <p>{{ $card['caption'] }}</p>
                    </article>
                @endcan
            @endforeach
        </section>

        <section class="dp-charts-grid" aria-label="Gráficos estadísticos">
            @can('admin.quotes.index')
                <article class="dp-chart-card">
                    <div class="dp-chart-heading">
                        <div>
                            <span>Estadística comercial</span>
                            <h2>Cotizaciones por mes</h2>
                        </div>
                        <p>Resumen mensual de propuestas comerciales generadas.</p>
                    </div>
                    <div class="dp-chart-canvas">
                        <canvas id="quotesMonthlyChart"></canvas>
                    </div>
                </article>
            @endcan

            @can('admin.warehouse-entries.index')
                <article class="dp-chart-card">
                    <div class="dp-chart-heading">
                        <div>
                            <span>Control de almacén</span>
                            <h2>Ingresos valorizados por mes</h2>
                        </div>
                        <p>Valor mensual de mercadería registrada en almacén.</p>
                    </div>
                    <div class="dp-chart-canvas">
                        <canvas id="warehouseEntriesChart"></canvas>
                    </div>
                </article>
            @endcan

            @canany(['admin.customer-purchase-orders.index', 'admin.supplier-purchase-orders.index'])
                <article class="dp-chart-card dp-chart-card-wide">
                    <div class="dp-chart-heading">
                        <div>
                            <span>Comparativo operativo</span>
                            <h2>Órdenes de compra por mes</h2>
                        </div>
                        <p>Comparativo entre pedidos de clientes y compras a proveedores.</p>
                    </div>
                    <div class="dp-chart-canvas">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </article>
            @endcanany
        </section>

        @canany([
            'admin.quotes.store',
            'admin.market-studies.store',
            'admin.customer-purchase-orders.store',
            'admin.supplier-purchase-orders.store',
            'admin.warehouse-entries.store',
            'admin.kardex.index',
        ])
            <section class="dp-panel">
                <div class="dp-panel-heading">
                    <div>
                        <span>Accesos rápidos</span>
                        <h2>Continúa la operación</h2>
                    </div>
                    <p>Atajos a los módulos principales según permisos del usuario.</p>
                </div>

                <div class="dp-quick-grid">
                    @foreach ($quickLinks as $link)
                        @can($link['permission'])
                            @if ($link['route'])
                                <a href="{{ $link['route'] }}" class="dp-quick-card">
                                    <i class="{{ $link['icon'] }}"></i>
                                    <strong>{{ $link['title'] }}</strong>
                                    <span>{{ $link['description'] }}</span>
                                </a>
                            @endif
                        @endcan
                    @endforeach
                </div>
            </section>
        @endcanany

        <section class="dp-dashboard-grid">
            @canany(['admin.quotes.index', 'admin.supplier-purchase-orders.index', 'admin.warehouse-entries.index', 'admin.kardex.index'])
                <div class="dp-panel">
                    <div class="dp-panel-heading compact">
                        <div>
                            <span>Operación del día</span>
                            <h2>Últimos movimientos</h2>
                        </div>
                    </div>

                    <div class="dp-operation-list">
                        @foreach ($operationSections as $section)
                            @can($section['permission'])
                                <article class="dp-operation-card">
                                    <div class="dp-operation-title">
                                        <i class="{{ $section['icon'] }}"></i>
                                        <strong>{{ $section['title'] }}</strong>
                                    </div>

                                    @forelse ($section['items'] as $item)
                                        <a href="{{ $section['route'] }}" class="dp-operation-item">
                                            <span>
                                                <strong>{{ $section['label']($item) }}</strong>
                                                <small>{{ $section['detail']($item) }}</small>
                                            </span>
                                            <em>{{ $section['meta']($item) }}</em>
                                        </a>
                                    @empty
                                        <div class="dp-empty-state">
                                            <i class="far fa-folder-open"></i>
                                            {{ $section['empty'] }}
                                        </div>
                                    @endforelse
                                </article>
                            @endcan
                        @endforeach
                    </div>
                </div>
            @endcanany

            <aside class="dp-alert-panel">
                <div class="dp-panel-heading compact">
                    <div>
                        <span>Alertas operativas</span>
                        <h2>Control y seguimiento</h2>
                    </div>
                </div>

                @php $visibleAlerts = 0; @endphp

                @foreach ($alerts as $alert)
                    @can($alert['permission'])
                        @php $visibleAlerts++; @endphp
                        <div class="dp-alert-item">
                            <i class="{{ $alert['icon'] }}"></i>
                            <span>
                                <strong>{{ $alert['title'] }}</strong>
                                <small>{{ $alert['description'] }}</small>
                            </span>
                        </div>
                    @endcan
                @endforeach

                @if ($visibleAlerts === 0)
                    <div class="dp-empty-state">
                        <i class="fas fa-check-circle"></i>
                        Sin alertas críticas por el momento.
                    </div>
                @endif
            </aside>
        </section>

        <section class="dp-trace-panel">
            <div class="dp-trace-content">
                <span class="dp-panel-kicker">Trazabilidad documental</span>
                <h2>Del estudio de mercado al Kardex, en un flujo controlado.</h2>
                <p>
                    Cada proceso comercial, documental y logístico mantiene una secuencia clara
                    para sostener el abastecimiento institucional de medicamentos y dispositivos médicos.
                </p>

                <div class="dp-flow">
                    <span><i class="fas fa-search-dollar"></i> Estudio</span>
                    <span><i class="fas fa-file-invoice-dollar"></i> Cotización</span>
                    <span><i class="fas fa-file-signature"></i> Orden</span>
                    <span><i class="fas fa-warehouse"></i> Ingreso</span>
                    <span><i class="fas fa-boxes"></i> Kardex</span>
                    <span><i class="fas fa-folder-open"></i> Documento</span>
                </div>
            </div>
        </section>
    </div>
@stop

@push('css')
    <style>
        :root {
            --dp-primary: #0f766e;
            --dp-primary-dark: #115e59;
            --dp-accent: #16a34a;
            --dp-blue: #0284c7;
            --dp-cyan: #0891b2;
            --dp-warning: #f59e0b;
            --dp-danger: #ef4444;
            --dp-bg: #f4f7fb;
            --dp-card: #ffffff;
            --dp-text: #0f172a;
            --dp-muted: #64748b;
            --dp-border: #e5e7eb;
        }

        .dp-content-wrapper {
            background: var(--dp-bg) !important;
        }

        .dp-dashboard {
            padding-bottom: 26px;
        }

        .dp-dashboard-hero {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 330px;
            gap: 24px;
            align-items: center;
            padding: 26px;
            border: 1px solid rgba(15, 118, 110, .12);
            border-radius: 24px;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, .97), rgba(236, 253, 245, .92)),
                radial-gradient(circle at 88% 8%, rgba(8, 145, 178, .16), transparent 32%);
            box-shadow: 0 20px 46px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .dp-dashboard-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(15, 118, 110, .07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 118, 110, .07) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(90deg, rgba(0, 0, 0, .48), transparent 78%);
            pointer-events: none;
        }

        .dp-hero-copy {
            position: relative;
            z-index: 1;
        }

        .dp-hero-kicker,
        .dp-panel-heading span,
        .dp-panel-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--dp-primary);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .dp-dashboard-hero h1 {
            margin: 10px 0 8px;
            color: var(--dp-text);
            font-size: 34px;
            font-weight: 900;
            line-height: 1.12;
        }

        .dp-dashboard-hero p {
            max-width: 760px;
            margin: 0;
            color: var(--dp-muted);
            font-size: 15px;
            line-height: 1.65;
        }

        .dp-hero-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 17px;
        }

        .dp-hero-badges span {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 999px;
            color: #315243;
            background: rgba(255, 255, 255, .78);
            font-size: 13px;
            font-weight: 800;
        }

        .dp-hero-chart-card {
            position: relative;
            z-index: 1;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 20px;
            color: #fff;
            background: linear-gradient(135deg, var(--dp-text), var(--dp-primary-dark));
            box-shadow: 0 18px 38px rgba(15, 23, 42, .18);
        }

        .dp-hero-chart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .dp-hero-chart-head span {
            color: rgba(255, 255, 255, .68);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .dp-hero-chart-head strong {
            font-size: 14px;
        }

        .dp-hero-bars {
            display: flex;
            align-items: end;
            gap: 12px;
            height: 118px;
            padding: 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .12);
        }

        .dp-hero-bars span {
            flex: 1;
            min-height: 12%;
            height: var(--bar-height);
            border-radius: 10px 10px 4px 4px;
            background: linear-gradient(180deg, #86efac, #0f766e);
            box-shadow: 0 10px 22px rgba(0, 0, 0, .18);
        }

        .dp-hero-chart-legend {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-top: 12px;
            color: rgba(255, 255, 255, .72);
            font-size: 11px;
            font-weight: 700;
            text-align: center;
        }

        .dp-metric-grid,
        .dp-quick-grid,
        .dp-operation-list {
            display: grid;
            gap: 16px;
        }

        .dp-metric-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-bottom: 18px;
        }

        .dp-charts-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 18px;
        }

        .dp-metric-card,
        .dp-panel,
        .dp-alert-panel,
        .dp-trace-panel,
        .dp-chart-card {
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 22px;
            background: var(--dp-card);
            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
        }

        .dp-chart-card {
            padding: 22px;
        }

        .dp-chart-card-wide {
            grid-column: 1 / -1;
        }

        .dp-chart-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .dp-chart-heading span {
            display: inline-flex;
            color: var(--dp-primary);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .dp-chart-heading h2 {
            margin: 5px 0 0;
            color: var(--dp-text);
            font-size: 20px;
            font-weight: 900;
        }

        .dp-chart-heading p {
            max-width: 360px;
            margin: 0;
            color: var(--dp-muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .dp-chart-canvas {
            position: relative;
            height: 280px;
        }

        .dp-chart-canvas canvas {
            width: 100%;
            height: 100%;
            display: block;
        }

        .dp-metric-card {
            position: relative;
            min-height: 190px;
            padding: 20px;
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .dp-metric-card::after {
            content: "";
            position: absolute;
            right: -32px;
            top: -34px;
            width: 96px;
            height: 96px;
            border-radius: 28px;
            background: var(--metric-soft, #e6f7f6);
            transform: rotate(18deg);
        }

        .dp-metric-card:hover,
        .dp-quick-card:hover,
        .dp-operation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 42px rgba(15, 23, 42, .10);
        }

        .dp-metric-top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }

        .dp-metric-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 16px;
            color: var(--metric-color, var(--dp-primary));
            background: var(--metric-soft, #e6f7f6);
            font-size: 20px;
        }

        .dp-metric-pill {
            padding: 6px 9px;
            border-radius: 999px;
            color: var(--metric-color, var(--dp-primary));
            background: var(--metric-soft, #e6f7f6);
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
        }

        .dp-metric-card small,
        .dp-metric-card strong,
        .dp-metric-card p {
            position: relative;
            z-index: 1;
            display: block;
        }

        .dp-metric-card small {
            color: var(--dp-muted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .dp-metric-card strong {
            margin: 6px 0;
            color: var(--dp-text);
            font-size: 28px;
            font-weight: 900;
            line-height: 1.1;
        }

        .dp-metric-card p {
            margin: 0;
            color: var(--dp-muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .metric-teal { --metric-color: var(--dp-primary); --metric-soft: #e6f7f6; }
        .metric-green { --metric-color: var(--dp-accent); --metric-soft: #e8f7ef; }
        .metric-emerald { --metric-color: #059669; --metric-soft: #e6f8f1; }
        .metric-cyan { --metric-color: var(--dp-cyan); --metric-soft: #e7f7fb; }
        .metric-warning { --metric-color: var(--dp-warning); --metric-soft: #fff7e6; }
        .metric-dark { --metric-color: var(--dp-primary-dark); --metric-soft: #e6f7f6; }

        .dp-panel,
        .dp-alert-panel {
            margin-bottom: 18px;
            padding: 22px;
        }

        .dp-panel-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .dp-panel-heading.compact {
            align-items: flex-start;
        }

        .dp-panel-heading h2,
        .dp-trace-content h2 {
            margin: 5px 0 0;
            color: var(--dp-text);
            font-size: 22px;
            font-weight: 900;
        }

        .dp-panel-heading p,
        .dp-trace-content p {
            max-width: 460px;
            margin: 0;
            color: var(--dp-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .dp-quick-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }

        .dp-quick-card {
            min-height: 145px;
            padding: 17px;
            border: 1px solid rgba(15, 118, 110, .12);
            border-radius: 18px;
            color: var(--dp-text);
            background: linear-gradient(135deg, #fff, #f8fcfb);
            text-decoration: none;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .dp-quick-card:hover {
            border-color: rgba(15, 118, 110, .25);
            color: var(--dp-text);
            text-decoration: none;
        }

        .dp-quick-card i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            margin-bottom: 13px;
            border-radius: 14px;
            color: var(--dp-primary);
            background: #e6f7f6;
            font-size: 17px;
        }

        .dp-quick-card strong,
        .dp-quick-card span {
            display: block;
        }

        .dp-quick-card strong {
            font-size: 14px;
            line-height: 1.25;
        }

        .dp-quick-card span {
            margin-top: 6px;
            color: var(--dp-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .dp-dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 18px;
        }

        .dp-operation-list {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dp-operation-card {
            padding: 16px;
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 18px;
            background: #f8fafc;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .dp-operation-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
            color: var(--dp-text);
        }

        .dp-operation-title i {
            color: var(--dp-primary);
        }

        .dp-operation-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 0;
            border-top: 1px solid var(--dp-border);
            color: var(--dp-text);
            text-decoration: none;
        }

        .dp-operation-item:hover {
            color: var(--dp-primary);
            text-decoration: none;
        }

        .dp-operation-item strong,
        .dp-operation-item small {
            display: block;
        }

        .dp-operation-item strong {
            font-size: 13px;
        }

        .dp-operation-item small {
            margin-top: 3px;
            color: var(--dp-muted);
            font-size: 12px;
        }

        .dp-operation-item em {
            color: var(--dp-primary-dark);
            font-size: 12px;
            font-style: normal;
            font-weight: 900;
            white-space: nowrap;
        }

        .dp-alert-panel {
            align-self: start;
        }

        .dp-alert-item {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 12px;
            padding: 14px 0;
            border-top: 1px solid #eef2f7;
        }

        .dp-alert-item i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 13px;
            color: var(--dp-primary);
            background: #e6f7f6;
        }

        .dp-alert-item strong,
        .dp-alert-item small {
            display: block;
        }

        .dp-alert-item strong {
            color: var(--dp-text);
            font-size: 14px;
        }

        .dp-alert-item small {
            margin-top: 4px;
            color: var(--dp-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .dp-trace-panel {
            padding: 24px;
            background:
                linear-gradient(135deg, #ffffff, #f8fcfb),
                radial-gradient(circle at top right, rgba(15, 118, 110, .08), transparent 28%);
        }

        .dp-flow {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-top: 20px;
        }

        .dp-flow span {
            position: relative;
            min-height: 72px;
            padding: 13px;
            border: 1px solid rgba(15, 118, 110, .13);
            border-radius: 16px;
            color: var(--dp-text);
            background: #fff;
            font-size: 12px;
            font-weight: 900;
            text-align: center;
        }

        .dp-flow span:not(:last-child)::after {
            content: "";
            position: absolute;
            top: 50%;
            right: -11px;
            width: 12px;
            height: 2px;
            background: rgba(15, 118, 110, .30);
        }

        .dp-flow i {
            display: block;
            margin-bottom: 7px;
            color: var(--dp-primary);
            font-size: 18px;
        }

        .dp-empty-state {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 14px;
            border: 1px dashed rgba(100, 116, 139, .28);
            border-radius: 14px;
            color: var(--dp-muted);
            background: #fff;
            font-size: 13px;
            font-weight: 700;
        }

        @media (max-width: 1390px) {
            .dp-metric-grid,
            .dp-quick-grid,
            .dp-charts-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .dp-chart-card-wide {
                grid-column: auto;
            }
        }

        @media (max-width: 1100px) {
            .dp-dashboard-hero,
            .dp-dashboard-grid,
            .dp-trace-panel,
            .dp-charts-grid {
                grid-template-columns: 1fr;
            }

            .dp-operation-list {
                grid-template-columns: 1fr;
            }

            .dp-flow {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .dp-dashboard-hero,
            .dp-panel,
            .dp-alert-panel,
            .dp-trace-panel,
            .dp-chart-card {
                padding: 18px;
                border-radius: 18px;
            }

            .dp-dashboard-hero h1 {
                font-size: 26px;
            }

            .dp-panel-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .dp-metric-grid,
            .dp-quick-grid,
            .dp-flow {
                grid-template-columns: 1fr;
            }

            .dp-chart-heading {
                flex-direction: column;
            }

            .dp-chart-canvas {
                height: 240px;
            }

            .dp-flow span:not(:last-child)::after {
                display: none;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const months = @json($months);
            const chartSets = [
                {
                    id: 'quotesMonthlyChart',
                    labels: months,
                    datasets: [
                        {
                            label: 'Cotizaciones',
                            data: @json($quotesChartData),
                            color: '#0f766e'
                        }
                    ]
                },
                {
                    id: 'warehouseEntriesChart',
                    labels: months,
                    currency: true,
                    datasets: [
                        {
                            label: 'Ingresos valorizados',
                            data: @json($warehouseEntriesChartData),
                            color: '#0891b2'
                        }
                    ]
                },
                {
                    id: 'ordersChart',
                    labels: months,
                    datasets: [
                        {
                            label: 'Órdenes cliente',
                            data: @json($customerOrdersChartData),
                            color: '#16a34a'
                        },
                        {
                            label: 'Órdenes proveedor',
                            data: @json($supplierOrdersChartData),
                            color: '#0284c7'
                        }
                    ]
                }
            ];

            function drawBarChart(config) {
                const canvas = document.getElementById(config.id);

                if (!canvas) {
                    return;
                }

                const ctx = canvas.getContext('2d');
                const ratio = window.devicePixelRatio || 1;
                const rect = canvas.parentElement.getBoundingClientRect();
                const width = Math.max(rect.width, 320);
                const height = Math.max(rect.height, 220);
                const padding = { top: 24, right: 18, bottom: 48, left: 42 };
                const chartWidth = width - padding.left - padding.right;
                const chartHeight = height - padding.top - padding.bottom;
                const allValues = config.datasets.flatMap(dataset => dataset.data.map(Number));
                const maxValue = Math.max(...allValues, 0);
                const scaleMax = maxValue > 0 ? maxValue * 1.18 : 1;

                canvas.width = width * ratio;
                canvas.height = height * ratio;
                canvas.style.width = width + 'px';
                canvas.style.height = height + 'px';
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.clearRect(0, 0, width, height);
                ctx.font = '12px "Segoe UI", Arial, sans-serif';
                ctx.lineWidth = 1;

                for (let i = 0; i <= 4; i++) {
                    const y = padding.top + (chartHeight / 4) * i;
                    const value = scaleMax - (scaleMax / 4) * i;
                    ctx.strokeStyle = '#eef2f7';
                    ctx.beginPath();
                    ctx.moveTo(padding.left, y);
                    ctx.lineTo(width - padding.right, y);
                    ctx.stroke();
                    ctx.fillStyle = '#94a3b8';
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(formatChartValue(value, config.currency), padding.left - 8, y);
                }

                const groupWidth = chartWidth / config.labels.length;
                const datasetCount = config.datasets.length;
                const barWidth = Math.min(22, (groupWidth - 16) / datasetCount);

                config.labels.forEach(function (label, monthIndex) {
                    const groupX = padding.left + groupWidth * monthIndex;
                    const barsWidth = barWidth * datasetCount;
                    const startX = groupX + (groupWidth - barsWidth) / 2;

                    config.datasets.forEach(function (dataset, datasetIndex) {
                        const value = Number(dataset.data[monthIndex] || 0);
                        const barHeight = value <= 0 ? 0 : (value / scaleMax) * chartHeight;
                        const x = startX + barWidth * datasetIndex;
                        const y = padding.top + chartHeight - barHeight;

                        roundRect(ctx, x, y, barWidth - 3, barHeight, 7, dataset.color);
                    });

                    ctx.fillStyle = '#64748b';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'top';
                    ctx.fillText(label, groupX + groupWidth / 2, padding.top + chartHeight + 14);
                });

                drawLegend(ctx, config.datasets, width, height);
            }

            function formatChartValue(value, currency) {
                if (currency) {
                    return 'S/ ' + Math.round(value).toLocaleString('es-PE');
                }

                return Math.round(value).toLocaleString('es-PE');
            }

            function roundRect(ctx, x, y, width, height, radius, color) {
                if (height <= 0) {
                    return;
                }

                const safeRadius = Math.min(radius, width / 2, height / 2);
                const gradient = ctx.createLinearGradient(0, y, 0, y + height);
                gradient.addColorStop(0, color);
                gradient.addColorStop(1, shadeColor(color, -18));

                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.moveTo(x + safeRadius, y);
                ctx.lineTo(x + width - safeRadius, y);
                ctx.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
                ctx.lineTo(x + width, y + height);
                ctx.lineTo(x, y + height);
                ctx.lineTo(x, y + safeRadius);
                ctx.quadraticCurveTo(x, y, x + safeRadius, y);
                ctx.closePath();
                ctx.fill();
            }

            function drawLegend(ctx, datasets, width, height) {
                const itemWidth = 150;
                const totalWidth = datasets.length * itemWidth;
                let x = Math.max(16, (width - totalWidth) / 2);
                const y = height - 18;

                datasets.forEach(function (dataset) {
                    ctx.fillStyle = dataset.color;
                    ctx.fillRect(x, y - 8, 20, 8);
                    ctx.fillStyle = '#64748b';
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(dataset.label, x + 28, y - 4);
                    x += itemWidth;
                });
            }

            function shadeColor(color, percent) {
                const number = parseInt(color.replace('#', ''), 16);
                const amount = Math.round(2.55 * percent);
                const r = Math.max(0, Math.min(255, (number >> 16) + amount));
                const g = Math.max(0, Math.min(255, ((number >> 8) & 0x00FF) + amount));
                const b = Math.max(0, Math.min(255, (number & 0x0000FF) + amount));

                return '#' + (0x1000000 + (r << 16) + (g << 8) + b).toString(16).slice(1);
            }

            function renderCharts() {
                chartSets.forEach(drawBarChart);
            }

            renderCharts();
            window.addEventListener('resize', debounce(renderCharts, 180));

            function debounce(callback, wait) {
                let timeout;
                return function () {
                    clearTimeout(timeout);
                    timeout = setTimeout(callback, wait);
                };
            }
        });
    </script>
@endpush

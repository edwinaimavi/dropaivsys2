@extends('layouts.app')

@section('subtitle', 'Centro de control')

@section('header')
@stop

@section('content_body')
    @php
        $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
        $monthlyOperations = $metrics['monthQuotes'] + $metrics['monthCustomerPurchaseOrders']
            + $metrics['monthSupplierPurchaseOrders'] + $metrics['monthWarehouseEntries'];

        $kpis = [
            ['permission' => 'admin.quotes.index', 'tone' => 'cyan', 'icon' => 'fas fa-file-invoice-dollar', 'label' => 'Cotizaciones', 'value' => number_format($metrics['totalQuotes']), 'meta' => $metrics['monthQuotes'] . ' este mes'],
            ['permission' => 'admin.customer-purchase-orders.index', 'tone' => 'emerald', 'icon' => 'fas fa-file-signature', 'label' => '&Oacute;rdenes de clientes', 'value' => number_format($metrics['totalCustomerPurchaseOrders']), 'meta' => $metrics['monthCustomerPurchaseOrders'] . ' este mes'],
            ['permission' => 'admin.supplier-purchase-orders.index', 'tone' => 'blue', 'icon' => 'fas fa-truck-loading', 'label' => '&Oacute;rdenes a proveedores', 'value' => number_format($metrics['totalSupplierPurchaseOrders']), 'meta' => $metrics['monthSupplierPurchaseOrders'] . ' este mes'],
            ['permission' => 'admin.warehouse-entries.index', 'tone' => 'violet', 'icon' => 'fas fa-warehouse', 'label' => 'Ingresos a almac&eacute;n', 'value' => number_format($metrics['totalWarehouseEntries']), 'meta' => $metrics['monthWarehouseEntries'] . ' este mes'],
            ['permission' => 'admin.kardex.index', 'tone' => 'amber', 'icon' => 'fas fa-boxes', 'label' => 'Art&iacute;culos con stock', 'value' => number_format($metrics['articlesWithStock']), 'meta' => $metrics['lowStockItems'] . ' con stock bajo'],
            ['permission' => 'admin.kardex.index', 'tone' => 'rose', 'icon' => 'fas fa-coins', 'label' => 'Valor de inventario', 'value' => $money($metrics['inventoryValue']), 'meta' => 'Valorizacización actual'],
        ];

        $flow = [
            ['icon' => 'fas fa-file-invoice-dollar', 'label' => 'Cotizaci&oacute;n', 'text' => 'Propuesta comercial'],
            ['icon' => 'fas fa-file-signature', 'label' => 'Orden cliente', 'text' => 'Demanda confirmada'],
            ['icon' => 'fas fa-shopping-cart', 'label' => 'Orden proveedor', 'text' => 'Abastecimiento'],
            ['icon' => 'fas fa-dolly-flatbed', 'label' => 'Ingreso', 'text' => 'Recepci&oacute;n y control'],
            ['icon' => 'fas fa-receipt', 'label' => 'Facturaci&oacute;n', 'text' => 'Comprobante electr&oacute;nico'],
            ['icon' => 'fas fa-exchange-alt', 'label' => 'Kardex', 'text' => 'Trazabilidad final'],
        ];

        $actions = [
            ['permission' => 'admin.quotes.store', 'route' => 'admin.quotes.index', 'icon' => 'fas fa-plus', 'label' => 'Nueva cotizaci&oacute;n', 'tone' => 'emerald'],
            ['permission' => 'admin.customer-purchase-orders.store', 'route' => 'admin.customer-purchase-orders.index', 'icon' => 'fas fa-file-signature', 'label' => 'Orden de cliente', 'tone' => 'blue'],
            ['permission' => 'admin.supplier-purchase-orders.store', 'route' => 'admin.supplier-purchase-orders.index', 'icon' => 'fas fa-shopping-cart', 'label' => 'Orden a proveedor', 'tone' => 'violet'],
            ['permission' => 'admin.warehouse-entries.store', 'route' => 'admin.warehouse-entries.index', 'icon' => 'fas fa-dolly-flatbed', 'label' => 'Ingreso a almac&eacute;n', 'tone' => 'cyan'],
            ['permission' => 'admin.electronic-invoices.index', 'route' => 'admin.electronic-invoices.index', 'icon' => 'fas fa-receipt', 'label' => 'Nuevo comprobante', 'tone' => 'amber'],
            ['permission' => 'admin.kardex.index', 'route' => 'admin.kardex.index', 'icon' => 'fas fa-exchange-alt', 'label' => 'Consultar Kardex', 'tone' => 'rose'],
        ];
    @endphp

    <main class="dashboard-executive">
        <section class="command-hero">
            <div class="command-hero__content">
                <span class="command-eyebrow"><i class="fas fa-satellite-dish"></i> Centro de comando operativo</span>
                <h1>{{ $greeting }}, <span>{{ $userName }}</span></h1>
                <p>Control integral del abastecimiento para instituciones, droguer&iacute;as y proveedores del Estado.</p>
                <div class="command-hero__actions">
                    @can('admin.quotes.store')
                        <a class="button-primary" href="{{ route('admin.quotes.index') }}"><i class="fas fa-plus"></i> Nueva cotizaci&oacute;n</a>
                    @endcan
                    @can('admin.kardex.index')
                        <a class="button-ghost" href="{{ route('admin.kardex.index') }}"><i class="fas fa-chart-line"></i> Ver inventario</a>
                    @endcan
                </div>
                <div class="command-trust">
                    <span><i class="fas fa-shield-alt"></i> Operaci&oacute;n segura</span>
                    <span><i class="fas fa-heartbeat"></i> Sector salud</span>
                    <span><i class="fas fa-landmark"></i> Contratación estatal</span>
                </div>
            </div>

            <aside class="operation-console">
                <div class="operation-console__head">
                    <div><small>Estado del sistema</small><strong>Operación activa</strong></div>
                    <span class="live-indicator"><i></i> EN LÍNEA</span>
                </div>
                <div class="operation-console__score">
                    <div class="score-ring"><span>{{ $monthlyOperations }}</span><small>mes</small></div>
                    <div><small>Movimientos operativos</small><strong>{{ ucfirst($todayLabel) }}</strong><span>Periodo {{ $dashboardYear }}</span></div>
                </div>
                <div class="operation-console__grid">
                    <div><i class="fas fa-box-open"></i><span>Stock activo</span><strong>{{ number_format($metrics['articlesWithStock']) }}</strong></div>
                    <div><i class="fas fa-coins"></i><span>Inventario</span><strong>{{ $money($metrics['inventoryValue']) }}</strong></div>
                    <div><i class="fas fa-exclamation-triangle"></i><span>Atenci&oacute;n</span><strong>{{ number_format($metrics['lowStockItems'] + $metrics['expiringStockItems']) }}</strong></div>
                </div>
            </aside>
        </section>

        <section class="dashboard-section kpi-section">
            <div class="section-heading"><div><span>Visión ejecutiva</span><h2>Indicadores clave</h2></div><p>Panorama acumulado y actividad del mes.</p></div>
            <div class="kpi-grid">
                @foreach ($kpis as $kpi)
                    @can($kpi['permission'])
                        <article class="executive-kpi executive-kpi--{{ $kpi['tone'] }}">
                            <div class="executive-kpi__icon"><i class="{{ $kpi['icon'] }}"></i></div>
                            <div class="executive-kpi__body"><span>{!! $kpi['label'] !!}</span><strong>{{ $kpi['value'] }}</strong><small><i class="fas fa-arrow-up"></i> {{ $kpi['meta'] }}</small></div>
                            <i class="executive-kpi__watermark {{ $kpi['icon'] }}"></i>
                        </article>
                    @endcan
                @endforeach
            </div>
        </section>

        <section class="dashboard-section process-section">
            <div class="section-heading"><div><span>Cadena de valor</span><h2>Flujo operativo</h2></div><p>Del requerimiento a la trazabilidad de inventario.</p></div>
            <div class="process-flow">
                @foreach ($flow as $step)
                    <article class="process-step"><div class="process-step__number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</div><div class="process-step__icon"><i class="{{ $step['icon'] }}"></i></div><strong>{!! $step['label'] !!}</strong><span>{!! $step['text'] !!}</span></article>
                @endforeach
            </div>
        </section>

        <section class="executive-workspace">
            <div class="analytics-column">
                <div class="section-heading"><div><span>Analítica</span><h2>Rendimiento anual</h2></div><p>Evoluci&oacute;n mensual de la operaci&oacute;n.</p></div>
                @can('admin.quotes.index')
                    <article class="analytics-card analytics-card--wide">
                        <div class="analytics-card__head"><div><span>Cotizaciones emitidas</span><strong>{{ number_format($metrics['totalQuotes']) }}</strong></div><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="chart-stage"><canvas data-dashboard-chart="quotes" aria-label="Gr&aacute;fico de cotizaciones"></canvas><div class="chart-empty"><i class="fas fa-chart-area"></i><strong>Sin actividad en este periodo</strong><span>Los nuevos registros aparecer&aacute;n aqu&iacute;.</span></div></div>
                    </article>
                @endcan
                <div class="analytics-pair">
                    @can('admin.customer-purchase-orders.index')
                        <article class="analytics-card"><div class="analytics-card__head"><div><span>&Oacute;rdenes de clientes</span><strong>{{ number_format($metrics['totalCustomerPurchaseOrders']) }}</strong></div><i class="fas fa-file-signature"></i></div><div class="chart-stage"><canvas data-dashboard-chart="customers"></canvas><div class="chart-empty"><i class="fas fa-chart-bar"></i><strong>Sin &oacute;rdenes registradas</strong><span>Esperando actividad.</span></div></div></article>
                    @endcan
                    @can('admin.warehouse-entries.index')
                        <article class="analytics-card"><div class="analytics-card__head"><div><span>Ingresos a almac&eacute;n</span><strong>{{ number_format($metrics['totalWarehouseEntries']) }}</strong></div><i class="fas fa-warehouse"></i></div><div class="chart-stage"><canvas data-dashboard-chart="warehouse"></canvas><div class="chart-empty"><i class="fas fa-chart-bar"></i><strong>Sin ingresos registrados</strong><span>Esperando actividad.</span></div></div></article>
                    @endcan
                </div>
            </div>

            <aside class="control-rail">
                <section class="rail-card quick-launcher">
                    <div class="rail-card__head"><div><span>Acceso directo</span><h2>Crear y consultar</h2></div><i class="fas fa-bolt"></i></div>
                    <div class="launcher-grid">
                        @foreach ($actions as $action)
                            @can($action['permission'])
                                <a class="launcher launcher--{{ $action['tone'] }}" href="{{ route($action['route']) }}"><i class="{{ $action['icon'] }}"></i><span>{{ $action['label'] }}</span><b class="fas fa-chevron-right"></b></a>
                            @endcan
                        @endforeach
                    </div>
                </section>

                <section class="rail-card alerts-panel">
                    <div class="rail-card__head"><div><span>Monitoreo</span><h2>Alertas operativas</h2></div><span class="alert-counter">{{ count($alerts) }}</span></div>
                    <div class="alert-list">
                        @forelse ($alerts as $alert)
                            @can($alert['permission'])
                                <div class="operational-alert"><i class="{{ $alert['icon'] }}"></i><div><strong>{{ $alert['title'] }}</strong><span>{{ $alert['description'] }}</span></div></div>
                            @endcan
                        @empty
                            <div class="all-clear"><i class="fas fa-check-circle"></i><div><strong>Todo bajo control</strong><span>No hay alertas operativas pendientes.</span></div></div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>
    </main>
@stop

@push('css')
    @vite('resources/css/dashboard.css')
@endpush

@php
    $dashboardPayload = [
        'months' => $months ?? [],
        'charts' => [
            'quotes' => $quotesChartData ?? [],
            'customers' => $customerOrdersChartData ?? [],
            'warehouse' => $warehouseEntriesChartData ?? [],
        ],
    ];
@endphp

@push('js')
    <script>
        window.DropaivDashboard = @json($dashboardPayload);
    </script>
    @vite('resources/js/pages/dashboard.js')
@endpush

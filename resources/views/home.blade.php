@extends('layouts.app')

@section('subtitle', 'Dashboard')

@section('header')
    <div class="container-fluid">
        <div class="dp-dashboard-hero">
            <div>
                <span class="dp-dashboard-kicker">
                    <i class="fas fa-shield-alt"></i>
                    Droguería Dropaiv | Proveedor del Estado
                </span>
                <h1>Bienvenido a DroPaivSys</h1>
                <p>
                    Panel de control para la gestión comercial, logística y documental de Droguería Dropaiv.
                </p>
                <div class="dp-user-line">
                    <span>Hola, <strong>{{ auth()->user()->name ?? 'usuario' }}</strong></span>
                    <span><i class="far fa-calendar-alt"></i> {{ now()->format('d/m/Y') }}</span>
                    @if (Route::has('settings.profile'))
                        <a href="{{ route('settings.profile') }}">
                            <i class="fas fa-user-circle"></i>
                            Mi perfil
                        </a>
                    @endif
                </div>
            </div>

            <div class="dp-hero-panel">
                <span class="dp-status-dot"></span>
                <strong>Plataforma activa</strong>
                <small>Control comercial, almacén, Kardex y trazabilidad desde un solo lugar.</small>
            </div>
        </div>
    </div>
@stop

@section('content_body')
    @php
        $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);

        $recentSections = [
            [
                'permission' => 'admin.quotes.index',
                'title' => 'Últimas cotizaciones',
                'icon' => 'fas fa-file-invoice-dollar',
                'route' => Route::has('admin.quotes.index') ? route('admin.quotes.index') : null,
                'items' => $latestQuotes,
                'empty' => 'No hay cotizaciones recientes.',
                'label' => fn ($item) => $item->quote_number ?? 'Cotización #' . $item->id,
                'detail' => fn ($item) => optional($item->customer)->business_name
                    ?? optional($item->customer)->full_name
                    ?? 'Cliente no registrado',
                'amount' => fn ($item) => $money($item->grand_total ?? 0),
            ],
            [
                'permission' => 'admin.warehouse-entries.index',
                'title' => 'Últimos ingresos',
                'icon' => 'fas fa-warehouse',
                'route' => Route::has('admin.warehouse-entries.index') ? route('admin.warehouse-entries.index') : null,
                'items' => $latestWarehouseEntries,
                'empty' => 'No hay ingresos de almacén recientes.',
                'label' => fn ($item) => $item->entry_number ?? 'Ingreso #' . $item->id,
                'detail' => fn ($item) => optional($item->supplier)->business_name
                    ?? optional($item->warehouse)->name
                    ?? 'Proveedor no registrado',
                'amount' => fn ($item) => $money($item->grand_total ?? 0),
            ],
            [
                'permission' => 'admin.supplier-purchase-orders.index',
                'title' => 'Órdenes a proveedores',
                'icon' => 'fas fa-truck-loading',
                'route' => Route::has('admin.supplier-purchase-orders.index') ? route('admin.supplier-purchase-orders.index') : null,
                'items' => $latestSupplierOrders,
                'empty' => 'No hay órdenes a proveedores recientes.',
                'label' => fn ($item) => $item->code ?? 'Orden #' . $item->id,
                'detail' => fn ($item) => optional($item->supplier)->business_name
                    ?? optional($item->supplier)->name
                    ?? 'Proveedor no registrado',
                'amount' => fn ($item) => $money($item->grand_total ?? 0),
            ],
        ];
    @endphp

    <div class="dp-dashboard">
        <section class="dp-metric-grid" aria-label="Indicadores principales">
            @can('admin.quotes.index')
                <article class="dp-metric-card accent-teal">
                    <span class="dp-metric-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    <small>Cotizaciones emitidas</small>
                    <strong>{{ number_format($metrics['totalQuotes']) }}</strong>
                    <p>Propuestas comerciales registradas.</p>
                </article>
            @endcan

            @can('admin.customer-purchase-orders.index')
                <article class="dp-metric-card accent-green">
                    <span class="dp-metric-icon"><i class="fas fa-file-signature"></i></span>
                    <small>Órdenes de clientes</small>
                    <strong>{{ number_format($metrics['totalCustomerPurchaseOrders']) }}</strong>
                    <p>Pedidos recibidos y documentados.</p>
                </article>
            @endcan

            @can('admin.supplier-purchase-orders.index')
                <article class="dp-metric-card accent-emerald">
                    <span class="dp-metric-icon"><i class="fas fa-truck-loading"></i></span>
                    <small>Órdenes a proveedores</small>
                    <strong>{{ number_format($metrics['totalSupplierPurchaseOrders']) }}</strong>
                    <p>Compras gestionadas para abastecimiento.</p>
                </article>
            @endcan

            @can('admin.warehouse-entries.index')
                <article class="dp-metric-card accent-cyan">
                    <span class="dp-metric-icon"><i class="fas fa-warehouse"></i></span>
                    <small>Ingresos de almacén</small>
                    <strong>{{ number_format($metrics['totalWarehouseEntries']) }}</strong>
                    <p>Recepciones físicas registradas.</p>
                </article>
            @endcan

            @can('admin.kardex.index')
                <article class="dp-metric-card accent-amber">
                    <span class="dp-metric-icon"><i class="fas fa-boxes"></i></span>
                    <small>Artículos con stock</small>
                    <strong>{{ number_format($metrics['articlesWithStock']) }}</strong>
                    <p>Ítems disponibles en inventario.</p>
                </article>

                <article class="dp-metric-card accent-dark">
                    <span class="dp-metric-icon"><i class="fas fa-chart-line"></i></span>
                    <small>Valor de inventario</small>
                    <strong>{{ $money($metrics['inventoryValue']) }}</strong>
                    <p>Valorización actual del stock.</p>
                </article>
            @endcan
        </section>

        @canany([
            'admin.quotes.store',
            'admin.market-studies.store',
            'admin.customer-purchase-orders.store',
            'admin.supplier-purchase-orders.store',
            'admin.warehouse-entries.store',
            'admin.kardex.index',
        ])
            <section class="dp-section-card">
                <div class="dp-section-heading">
                    <div>
                        <span>Accesos rápidos</span>
                        <h2>Continúa el flujo operativo</h2>
                    </div>
                    <p>Atajos a los módulos principales respetando los permisos del usuario.</p>
                </div>

                <div class="dp-quick-grid">
                    @can('admin.quotes.store')
                        <a href="{{ route('admin.quotes.index') }}" class="dp-quick-card">
                            <i class="fas fa-plus-circle"></i>
                            <strong>Nueva Cotización</strong>
                            <span>Crear propuesta comercial para cliente.</span>
                        </a>
                    @endcan

                    @can('admin.market-studies.store')
                        <a href="{{ route('admin.market-studies.index') }}" class="dp-quick-card">
                            <i class="fas fa-search-dollar"></i>
                            <strong>Nuevo Estudio de Mercado</strong>
                            <span>Centralizar requerimientos y proveedores.</span>
                        </a>
                    @endcan

                    @can('admin.customer-purchase-orders.store')
                        <a href="{{ route('admin.customer-purchase-orders.index') }}" class="dp-quick-card">
                            <i class="fas fa-file-contract"></i>
                            <strong>Orden de Compra Cliente</strong>
                            <span>Registrar pedido recibido.</span>
                        </a>
                    @endcan

                    @can('admin.supplier-purchase-orders.store')
                        <a href="{{ route('admin.supplier-purchase-orders.index') }}" class="dp-quick-card">
                            <i class="fas fa-shipping-fast"></i>
                            <strong>Orden a Proveedor</strong>
                            <span>Gestionar compra de abastecimiento.</span>
                        </a>
                    @endcan

                    @can('admin.warehouse-entries.store')
                        <a href="{{ route('admin.warehouse-entries.index') }}" class="dp-quick-card">
                            <i class="fas fa-dolly-flatbed"></i>
                            <strong>Nuevo Ingreso</strong>
                            <span>Registrar mercadería recibida.</span>
                        </a>
                    @endcan

                    @can('admin.kardex.index')
                        <a href="{{ route('admin.kardex.index') }}" class="dp-quick-card">
                            <i class="fas fa-layer-group"></i>
                            <strong>Ver Kardex</strong>
                            <span>Consultar movimientos y stock valorizado.</span>
                        </a>
                    @endcan
                </div>
            </section>
        @endcanany

        <section class="dp-operation-grid" aria-label="Resumen operativo">
            <article>
                <i class="fas fa-handshake"></i>
                <strong>Comercial</strong>
                <span>Estudios de mercado, cotizaciones y adjudicaciones.</span>
            </article>
            <article>
                <i class="fas fa-shopping-cart"></i>
                <strong>Compras</strong>
                <span>Órdenes de compra a proveedores y seguimiento.</span>
            </article>
            <article>
                <i class="fas fa-warehouse"></i>
                <strong>Almacén</strong>
                <span>Ingresos, stock, lotes y vencimientos.</span>
            </article>
            <article>
                <i class="fas fa-clipboard-list"></i>
                <strong>Kardex</strong>
                <span>Movimientos, saldos y valorización.</span>
            </article>
        </section>

        <div class="dp-dashboard-columns">
            @canany(['admin.quotes.index', 'admin.warehouse-entries.index', 'admin.supplier-purchase-orders.index'])
                <section class="dp-section-card">
                    <div class="dp-section-heading compact">
                        <div>
                            <span>Últimos movimientos</span>
                            <h2>Actividad reciente</h2>
                        </div>
                    </div>

                    <div class="dp-recent-grid">
                        @foreach ($recentSections as $section)
                            @can($section['permission'])
                                <article class="dp-recent-card">
                                    <div class="dp-recent-title">
                                        <i class="{{ $section['icon'] }}"></i>
                                        <strong>{{ $section['title'] }}</strong>
                                    </div>

                                    @forelse ($section['items'] as $item)
                                        <a href="{{ $section['route'] ?? '#' }}" class="dp-recent-item">
                                            <span>
                                                <strong>{{ $section['label']($item) }}</strong>
                                                <small>{{ $section['detail']($item) }}</small>
                                            </span>
                                            <em>{{ $section['amount']($item) }}</em>
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
                </section>
            @endcanany

            <aside class="dp-alert-card">
                <div class="dp-section-heading compact">
                    <div>
                        <span>Recordatorios</span>
                        <h2>Alertas operativas</h2>
                    </div>
                </div>

                @can('admin.quotes.index')
                    <div class="dp-alert-item">
                        <i class="fas fa-clock"></i>
                        <span>
                            <strong>{{ number_format($metrics['expiringQuotes']) }} cotizaciones por vencer</strong>
                            <small>Vigencia dentro de los próximos 7 días.</small>
                        </span>
                    </div>
                @endcan

                @can('admin.kardex.index')
                    <div class="dp-alert-item">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>
                            <strong>{{ number_format($metrics['lowStockItems']) }} productos con stock bajo</strong>
                            <small>Según el mínimo configurado en inventario.</small>
                        </span>
                    </div>

                    <div class="dp-alert-item">
                        <i class="fas fa-calendar-times"></i>
                        <span>
                            <strong>{{ number_format($metrics['expiringStockItems']) }} productos próximos a vencer</strong>
                            <small>Vencimiento dentro de los próximos 30 días.</small>
                        </span>
                    </div>
                @endcan

                @can('admin.market-studies.index')
                    <div class="dp-alert-item">
                        <i class="fas fa-search"></i>
                        <span>
                            <strong>{{ number_format($latestMarketStudies->count()) }} estudios recientes en vista rápida</strong>
                            <small>Últimos registros disponibles para seguimiento.</small>
                        </span>
                    </div>
                @endcan

                @cannot('admin.quotes.index')
                    @cannot('admin.kardex.index')
                        @cannot('admin.market-studies.index')
                            <div class="dp-empty-state">
                                <i class="fas fa-lock"></i>
                                No hay alertas visibles para tus permisos actuales.
                            </div>
                        @endcannot
                    @endcannot
                @endcannot
            </aside>
        </div>
    </div>
@stop

@push('css')
    <style>
        .dp-content-wrapper {
            background: #f4f8fb !important;
        }

        .dp-dashboard {
            padding-bottom: 22px;
        }

        .dp-dashboard-hero {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 24px;
            align-items: center;
            padding: 26px;
            border: 1px solid rgba(15, 118, 110, .10);
            border-radius: 24px;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, .96), rgba(240, 253, 250, .92)),
                radial-gradient(circle at top right, rgba(22, 163, 74, .16), transparent 34%);
            box-shadow: 0 18px 44px rgba(15, 23, 42, .07);
            overflow: hidden;
        }

        .dp-dashboard-hero::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -78px;
            width: 220px;
            height: 220px;
            border: 34px solid rgba(15, 118, 110, .07);
            border-radius: 50%;
        }

        .dp-dashboard-kicker,
        .dp-section-heading span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #0f766e;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .dp-dashboard-hero h1 {
            position: relative;
            margin: 10px 0 8px;
            color: #0f172a;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.12;
        }

        .dp-dashboard-hero p {
            position: relative;
            max-width: 760px;
            margin: 0;
            color: #64748b;
            font-size: 15px;
            line-height: 1.65;
        }

        .dp-user-line {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .dp-user-line span,
        .dp-user-line a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 999px;
            color: #475569;
            background: rgba(255, 255, 255, .78);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .dp-user-line a {
            color: #0f766e;
        }

        .dp-hero-panel {
            position: relative;
            z-index: 1;
            min-height: 150px;
            padding: 22px;
            border-radius: 20px;
            color: #fff;
            background: linear-gradient(135deg, #0f172a, #115e59);
            box-shadow: 0 18px 38px rgba(15, 23, 42, .18);
        }

        .dp-hero-panel strong,
        .dp-hero-panel small {
            display: block;
        }

        .dp-hero-panel strong {
            margin: 14px 0 8px;
            font-size: 20px;
        }

        .dp-hero-panel small {
            color: rgba(255, 255, 255, .76);
            line-height: 1.55;
        }

        .dp-status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #86efac;
            box-shadow: 0 0 0 7px rgba(134, 239, 172, .14);
        }

        .dp-metric-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .dp-metric-card,
        .dp-section-card,
        .dp-alert-card,
        .dp-operation-grid article {
            border: 1px solid rgba(148, 163, 184, .18);
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
        }

        .dp-metric-card {
            position: relative;
            min-height: 184px;
            padding: 20px;
            border-radius: 20px;
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .dp-metric-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 5px;
            background: var(--card-accent, #0f766e);
        }

        .dp-metric-card:hover,
        .dp-quick-card:hover,
        .dp-operation-grid article:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 42px rgba(15, 23, 42, .10);
        }

        .dp-metric-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            margin-bottom: 15px;
            border-radius: 16px;
            color: var(--card-accent, #0f766e);
            background: var(--card-soft, #e6f7f6);
            font-size: 20px;
        }

        .dp-metric-card small,
        .dp-metric-card strong,
        .dp-metric-card p {
            display: block;
        }

        .dp-metric-card small {
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .dp-metric-card strong {
            margin: 6px 0;
            color: #0f172a;
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
        }

        .dp-metric-card p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }

        .accent-teal { --card-accent: #0f766e; --card-soft: #e6f7f6; }
        .accent-green { --card-accent: #16a34a; --card-soft: #e8f7ef; }
        .accent-emerald { --card-accent: #059669; --card-soft: #e6f8f1; }
        .accent-cyan { --card-accent: #0284c7; --card-soft: #e9f5fb; }
        .accent-amber { --card-accent: #d97706; --card-soft: #fff7e6; }
        .accent-dark { --card-accent: #115e59; --card-soft: #e6f7f6; }

        .dp-section-card,
        .dp-alert-card {
            margin-bottom: 18px;
            padding: 22px;
            border-radius: 22px;
        }

        .dp-section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .dp-section-heading.compact {
            align-items: flex-start;
        }

        .dp-section-heading h2 {
            margin: 5px 0 0;
            color: #0f172a;
            font-size: 22px;
            font-weight: 800;
        }

        .dp-section-heading p {
            max-width: 420px;
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.55;
        }

        .dp-quick-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 14px;
        }

        .dp-quick-card {
            min-height: 142px;
            padding: 17px;
            border: 1px solid rgba(15, 118, 110, .12);
            border-radius: 18px;
            color: #0f172a;
            background: linear-gradient(135deg, #fff, #f8fcfb);
            text-decoration: none;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .dp-quick-card:hover {
            border-color: rgba(15, 118, 110, .25);
            color: #0f172a;
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
            color: #0f766e;
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
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
        }

        .dp-operation-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .dp-operation-grid article {
            min-height: 132px;
            padding: 19px;
            border-radius: 20px;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .dp-operation-grid i {
            color: #0f766e;
            font-size: 22px;
        }

        .dp-operation-grid strong,
        .dp-operation-grid span {
            display: block;
        }

        .dp-operation-grid strong {
            margin: 12px 0 6px;
            color: #0f172a;
            font-size: 16px;
        }

        .dp-operation-grid span {
            color: #64748b;
            font-size: 13px;
            line-height: 1.55;
        }

        .dp-dashboard-columns {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 18px;
        }

        .dp-recent-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .dp-recent-card {
            padding: 16px;
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 18px;
            background: #f8fafc;
        }

        .dp-recent-title {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .dp-recent-title i {
            color: #0f766e;
        }

        .dp-recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 0;
            border-top: 1px solid #e5e7eb;
            color: #0f172a;
            text-decoration: none;
        }

        .dp-recent-item:hover {
            color: #0f766e;
            text-decoration: none;
        }

        .dp-recent-item strong,
        .dp-recent-item small {
            display: block;
        }

        .dp-recent-item strong {
            font-size: 13px;
        }

        .dp-recent-item small {
            margin-top: 3px;
            color: #64748b;
            font-size: 12px;
        }

        .dp-recent-item em {
            color: #115e59;
            font-size: 12px;
            font-style: normal;
            font-weight: 800;
            white-space: nowrap;
        }

        .dp-alert-card {
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
            color: #0f766e;
            background: #e6f7f6;
        }

        .dp-alert-item strong,
        .dp-alert-item small {
            display: block;
        }

        .dp-alert-item strong {
            color: #0f172a;
            font-size: 14px;
        }

        .dp-alert-item small {
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
        }

        .dp-empty-state {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 14px;
            border: 1px dashed rgba(100, 116, 139, .28);
            border-radius: 14px;
            color: #64748b;
            background: #fff;
            font-size: 13px;
            font-weight: 600;
        }

        @media (max-width: 1390px) {
            .dp-metric-grid,
            .dp-quick-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .dp-recent-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1080px) {
            .dp-dashboard-hero,
            .dp-dashboard-columns {
                grid-template-columns: 1fr;
            }

            .dp-hero-panel {
                min-height: auto;
            }

            .dp-operation-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .dp-dashboard-hero {
                padding: 20px;
            }

            .dp-dashboard-hero h1 {
                font-size: 27px;
            }

            .dp-section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .dp-metric-grid,
            .dp-quick-grid,
            .dp-operation-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@extends('layouts.app')

@section('subtitle', 'Formato 12.1 - Unidades Físicas')

@section('header')
    <div class="container-fluid no-print">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="h3 mb-1 font-weight-bold text-dark">Formato 12.1 &mdash; Registro de Inventario Permanente en Unidades Físicas</h1>
                <small class="text-muted">Registro mensual construido exclusivamente desde los movimientos históricos del Kardex</small>
            </div>
            <a href="{{ route('admin.kardex.index') }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
                <i class="fas fa-arrow-left mr-1"></i> Volver al Kardex
            </a>
        </div>
    </div>
@stop

@section('content_body')
    <div class="container-fluid physical-register">
        @include('admin.kardex.partials.formato-12-1-modal', ['embedded' => false])
    </div>
@stop

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.querySelector('[data-format12-root]');
            const company = root?.querySelector('#company_id');
            const year = root?.querySelector('#year');
            const month = root?.querySelector('#month');
            const warehouse = root?.querySelector('#warehouse_id');
            const article = root?.querySelector('#article_id');

            if (!root || !company || !year || !month || !warehouse || !article) {
                return;
            }

            let requestController = null;
            const initialArticleId = String(@json($articleId ?: ''));

            const resetArticles = (message = null, selectedId = '') => {
                article.innerHTML = '';
                article.add(new Option('Todos', '', false, !selectedId));

                if (message) {
                    const messageOption = new Option(message, '', false, false);
                    messageOption.disabled = true;
                    article.add(messageOption);
                }
            };

            const loadArticles = async (preferredArticleId = '') => {
                if (!company.value || !warehouse.value || !year.value || !month.value) {
                    resetArticles('Seleccione empresa y almacén');
                    article.disabled = false;
                    return;
                }

                requestController?.abort();
                requestController = new AbortController();
                const currentValue = preferredArticleId || article.value || '';
                resetArticles('Cargando artículos...', currentValue);
                article.disabled = true;

                const params = new URLSearchParams({
                    company_id: company.value,
                    warehouse_id: warehouse.value,
                    year: year.value,
                    month: month.value,
                });

                try {
                    const response = await fetch(`${root.dataset.articlesUrl}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: requestController.signal,
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const payload = await response.json();
                    const articles = Array.isArray(payload.articles) ? payload.articles : [];
                    resetArticles(articles.length ? null : 'Sin artículos con movimientos o saldo inicial en el período', currentValue);

                    articles.forEach((item) => {
                        const id = String(item.id);
                        article.add(new Option(item.label, id, false, id === currentValue));
                    });

                    if (currentValue && !articles.some((item) => String(item.id) === currentValue)) {
                        article.value = '';
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        resetArticles('No se pudieron cargar los artículos');
                    }
                } finally {
                    article.disabled = false;
                }
            };

            [company, year, month, warehouse].forEach((control) => {
                control.addEventListener('change', () => loadArticles(''));
            });

            if (company.value && warehouse.value) {
                loadArticles(initialArticleId);
            }
        });
    </script>
@endpush

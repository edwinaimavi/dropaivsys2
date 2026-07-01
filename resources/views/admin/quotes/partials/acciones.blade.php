<div class="btn-group shadow-sm " role="group" aria-label="Acciones">

    {{-- EDIT --}}
    @can('admin.quotes.update')
    <button type="button" class="btn btn-outline-primary btn-sm editQuote mr-2" data-toggle="tooltip"
        title="Editar Cotizacion" data-id="{{ $quote->id }}">

        <i class="fas fa-pen"></i>

    </button>
    @endcan

    {{-- PDF --}}
    @can('admin.quotes.pdf')
    @if ($pdfUrl)
        <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm mr-2"
            data-toggle="tooltip" title="Ver PDF">

            <i class="fas fa-file-pdf"></i>

        </a>
    @else
        <button type="button" class="btn btn-outline-secondary btn-sm mr-2" data-toggle="tooltip"
            title="PDF no generado" disabled>

            <i class="fas fa-file-pdf"></i>

        </button>
    @endif
    @endcan

    {{-- DELETE --}}
    @can('admin.quotes.destroy')
    <button type="button" class="btn btn-outline-danger btn-sm deleteQuote mr-2" data-id="{{ $quote->id }}"
        data-toggle="tooltip" title="Eliminar Cotizacion">

        <i class="fas fa-trash"></i>

    </button>
    @endcan

</div>

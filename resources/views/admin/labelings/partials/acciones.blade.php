<div class="btn-group" role="group">
    @can('admin.labelings.show')
        <button type="button" class="btn btn-sm btn-outline-info viewLabeling mr-2" data-id="{{ $labeling->id }}"
            data-toggle="tooltip" title="Ver detalle">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.labelings.update')
        @if ($labeling->status === 'DRAFT')
            <button type="button" class="btn btn-sm btn-outline-primary editLabeling mr-2" data-id="{{ $labeling->id }}"
                data-toggle="tooltip" title="Editar">
                <i class="fas fa-edit"></i>
            </button>
        @endif
    @endcan

    @can('admin.labelings.pdf')
        <a href="{{ route('admin.labelings.pdf', $labeling->id) }}" target="_blank" rel="noopener"
            class="btn btn-sm btn-outline-success mr-2" data-toggle="tooltip" title="PDF">
            <i class="fas fa-file-pdf"></i>
        </a>
    @endcan

    @can('admin.labelings.destroy')
        @if ($labeling->status !== 'CANCELLED')
            <button type="button" class="btn btn-sm btn-outline-danger deleteLabeling" data-id="{{ $labeling->id }}"
                data-toggle="tooltip" title="Anular">
                <i class="fas fa-ban"></i>
            </button>
        @endif
    @endcan
</div>

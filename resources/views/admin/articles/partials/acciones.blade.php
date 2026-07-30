<x-table-actions-dropdown label="Acciones del artículo">
    <x-slot name="main">
        <button type="button" class="btn btn-sm btn-success dp-action-main viewArticle"
            data-id="{{ $article->id }}" title="Ver artículo">
            <i class="fas fa-eye mr-1"></i> Ver
        </button>
    </x-slot>
    <x-slot name="menu">
        <h6 class="dropdown-header">Acciones operativas</h6>
        <button type="button" class="dropdown-item editArticle" title="Editar artículo"
            data-id="{{ $article->id }}" data-code="{{ $article->code }}"
            data-category_id="{{ $article->category_id }}" data-subcategory_id="{{ $article->subcategory_id }}"
            data-brand_id="{{ $article->brand_id }}" data-presentation_id="{{ $article->presentation_id }}"
            data-unit_id="{{ $article->unit_id }}" data-legal_name="{{ $article->legal_name }}"
            data-commercial_name="{{ $article->commercial_name }}" data-billing_name="{{ $article->billing_name }}"
            data-is_taxable="{{ $article->is_taxable }}" data-minimum_stock="{{ $article->minimum_stock }}"
            data-has_batch="{{ $article->has_batch }}" data-has_expiration="{{ $article->has_expiration }}"
            data-observation="{{ $article->observation }}" data-status="{{ $article->status }}">
            <i class="fas fa-pen text-primary"></i> Editar artículo
        </button>
        <div class="dropdown-divider"></div>
        <h6 class="dropdown-header">Cierre / anulación</h6>
        <button type="button" class="dropdown-item text-danger deleteArticle" data-id="{{ $article->id }}">
            <i class="fas fa-trash"></i> Eliminar artículo
        </button>
    </x-slot>
</x-table-actions-dropdown>

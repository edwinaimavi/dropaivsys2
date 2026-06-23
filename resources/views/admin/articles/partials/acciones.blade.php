<div class="btn-group shadow-sm" role="group" aria-label="Actions">

    {{-- VIEW --}}
    <button type="button" class="btn btn-outline-info btn-sm viewArticle" data-toggle="tooltip" title="Ver Artículo"
        data-id="{{ $article->id }}">

        <i class="fas fa-eye"></i>

    </button>

    {{-- EDIT --}}
    <button type="button" class="btn btn-outline-primary btn-sm editArticle" data-toggle="tooltip"
        title="Editar Artículo" data-id="{{ $article->id }}" data-code="{{ $article->code }}"
        data-category_id="{{ $article->category_id }}" data-subcategory_id="{{ $article->subcategory_id }}"
        data-brand_id="{{ $article->brand_id }}" data-presentation_id="{{ $article->presentation_id }}"
        data-unit_id="{{ $article->unit_id }}" data-legal_name="{{ $article->legal_name }}"
        data-commercial_name="{{ $article->commercial_name }}" data-billing_name="{{ $article->billing_name }}"
        data-is_taxable="{{ $article->is_taxable }}" data-minimum_stock="{{ $article->minimum_stock }}"
        data-has_batch="{{ $article->has_batch }}" data-has_expiration="{{ $article->has_expiration }}"
        data-observation="{{ $article->observation }}" data-status="{{ $article->status }}">

        <i class="fas fa-pen"></i>

    </button>


    {{-- DELETE --}}
    <button type="button" class="btn btn-outline-danger btn-sm deleteArticle" data-id="{{ $article->id }}"
        data-toggle="tooltip" title="Eliminar Artículo">

        <i class="fas fa-trash"></i>

    </button>


</div>

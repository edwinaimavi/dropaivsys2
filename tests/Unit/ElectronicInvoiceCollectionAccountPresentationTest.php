<?php

it('muestra la empresa en las cuentas del modal de cobranza sin alterar el valor enviado', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Admin/ElectronicInvoiceController.php');
    $modal = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/collectionModal.blade.php');

    expect($controller)
        ->toContain("'company:id,business_name'")
        ->and($modal)
        ->toContain('id="eic_company_bank_account_id"')
        ->toContain('name="company_bank_account_id"')
        ->toContain('value="{{ $account->id }}"')
        ->toContain("\$account->company?->business_name ?: (\$account->account_holder ?: 'Empresa '.\$account->company_id)")
        ->toContain('{{ $account->bank?->short_name ?? $account->bank?->description }} - {{ $account->currency?->code }} - {{ $account->account_number }}')
        ->not->toContain('data-html');
});

it('conserva el filtrado y la seleccion Select2 por id de cuenta', function () {
    $root = dirname(__DIR__, 2);
    $pageJs = file_get_contents($root.'/resources/js/pages/electronic-invoice.js');

    expect($pageJs)
        ->toContain("dropdownParent: $('#electronicInvoiceCollectionModal')")
        ->toContain("String($(this).data('company-id')) === String(invoice.company_id)")
        ->toContain("$('#eic_company_bank_account_id').val('').trigger('change.select2')")
        ->toContain("const option = $('#eic_company_bank_account_id option:selected')")
        ->toContain("new FormData(form)");
});

it('moderniza el modal y el archivo sin cambiar los campos enviados', function () {
    $root = dirname(__DIR__, 2);
    $modal = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/collectionModal.blade.php');
    $pageJs = file_get_contents($root.'/resources/js/pages/electronic-invoice.js');
    $css = file_get_contents($root.'/resources/css/electronic-invoice-collection.css');

    expect($modal)
        ->toContain('electronic-invoice-collection-summary')
        ->toContain('electronic-invoice-collection-section')
        ->toContain('id="eic_proof" name="proof" type="file"')
        ->toContain('accept=".pdf,.jpg,.jpeg,.png,.webp"')
        ->toContain('for="eic_proof" id="eic_proof_action"')
        ->toContain('id="eic_proof_remove"')
        ->toContain('name="collection_date"')
        ->toContain('name="amount"')
        ->toContain('name="observation"')
        ->and($pageJs)
        ->toContain("import '../../css/electronic-invoice-collection.css'")
        ->toContain(".on('change.electronicInvoicePage', '#eic_proof'")
        ->toContain("$('#eic_proof_name').text(file.name)")
        ->toContain("$('#eic_proof').val('')")
        ->toContain('new FormData(form)')
        ->and($css)
        ->toContain('.electronic-invoice-file-uploader')
        ->toContain('.electronic-invoice-collection-modal .select2-results__option')
        ->toContain('@media (max-width: 575.98px)');
});

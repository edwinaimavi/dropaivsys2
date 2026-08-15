<?php

it('protege las operaciones ajax y formdata de caja chica con csrf', function () {
    $script = file_get_contents(resource_path('js/pages/petty-cash.js'));
    $expenseModal = file_get_contents(resource_path('views/admin/petty-cash/partials/expenseModal.blade.php'));
    $adminLteMaster = file_get_contents(base_path('vendor/jeroennoten/laravel-adminlte/resources/views/master.blade.php'));

    expect($adminLteMaster)
        ->toContain('<meta name="csrf-token" content="{{ csrf_token() }}">')
        ->and($expenseModal)->toContain('@csrf')
        ->and($script)->toContain('$.ajaxSetup({')
        ->and($script)->toContain("'X-CSRF-TOKEN': csrfToken()")
        ->and($script)->toContain("'Accept': 'application/json'")
        ->and($script)->toContain("options.data.append('_token', token)")
        ->and($script)->toContain('[401, 419]')
        ->and($script)->toContain('Tu sesión ha vencido o el formulario expiró. Recarga la página e intenta nuevamente.')
        ->and($script)->toContain("title: 'Sesión vencida'")
        ->and($script)->toContain("confirmButtonText: 'Recargar página'");
});

it('incluye csrf en todos los formularios mutables de caja chica', function () {
    foreach ([
        'approvedAmountModal.blade.php',
        'expenseApprovalModals.blade.php',
        'expenseModal.blade.php',
        'modal.blade.php',
        'receiptExchangeModal.blade.php',
        'replenishmentModal.blade.php',
    ] as $partial) {
        expect(file_get_contents(resource_path("views/admin/petty-cash/partials/{$partial}")))
            ->toContain('@csrf');
    }
});

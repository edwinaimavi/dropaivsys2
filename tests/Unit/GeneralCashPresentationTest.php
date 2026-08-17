<?php

it('integra Caja General como módulo financiero separado con todos sus modales y permisos', function () {
    $basePath = dirname(__DIR__, 2);
    $menu = file_get_contents($basePath.'/config/adminlte.php');
    $view = file_get_contents($basePath.'/resources/views/admin/general-cash/index.blade.php');
    $modals = file_get_contents($basePath.'/resources/views/admin/general-cash/partials/modals.blade.php');
    $script = file_get_contents($basePath.'/resources/js/pages/general-cash.js');
    $seeder = file_get_contents($basePath.'/database/seeders/RoleSeeder.php');

    expect($menu)->toContain("'text' => 'Caja General'")
        ->toContain("'url' => 'admin/general-cash'")
        ->and($view)->toContain('Total Caja General')
        ->toContain('Ingresos del periodo')
        ->toContain('Egresos del periodo')
        ->toContain('Saldo disponible')
        ->and($modals)->toContain('Ingresar efectivo desde banco')
        ->toContain('Origen y destino')
        ->toContain('Datos de la operación')
        ->toContain('Responsables')
        ->toContain('Sustento y observación')
        ->toContain('general-cash-funding-upload')
        ->toContain('generalCashFundingObservationCount')
        ->toContain('name="general_cash_box_id"')
        ->toContain('name="company_bank_account_id"')
        ->toContain('name="movement_date"')
        ->toContain('name="amount"')
        ->toContain('name="operation_number"')
        ->toContain('name="responsible_user_id"')
        ->toContain('name="responsible_name"')
        ->toContain('name="support_file"')
        ->toContain('name="observation"')
        ->toContain('Registrar gasto general')
        ->toContain('Arqueo / cierre de Caja General')
        ->toContain('Auditoría')
        ->and($script)->toContain('GENERAL_CASH_FUNDING')
        ->toContain('resetFundingVisuals')
        ->toContain('general_cash_funding_support_file')
        ->toContain("'Procesando...' : 'Ingresar efectivo'")
        ->toContain('Se reversarán tanto el banco como Caja General')
        ->and($seeder)->toContain('admin.general-cash.index')
        ->toContain('admin.general-cash.expenses.annul')
        ->toContain('admin.general-cash.reports');
});

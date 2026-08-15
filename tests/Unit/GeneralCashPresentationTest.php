<?php

it('integra Caja General como módulo financiero separado con todos sus modales y permisos', function () {
    $menu = file_get_contents(config_path('adminlte.php'));
    $view = file_get_contents(resource_path('views/admin/general-cash/index.blade.php'));
    $modals = file_get_contents(resource_path('views/admin/general-cash/partials/modals.blade.php'));
    $script = file_get_contents(resource_path('js/pages/general-cash.js'));
    $seeder = file_get_contents(database_path('seeders/RoleSeeder.php'));

    expect($menu)->toContain("'text' => 'Caja General'")
        ->toContain("'url' => 'admin/general-cash'")
        ->and($view)->toContain('Total Caja General')
        ->toContain('Ingresos del periodo')
        ->toContain('Egresos del periodo')
        ->toContain('Saldo disponible')
        ->and($modals)->toContain('Ingresar efectivo desde banco')
        ->toContain('Registrar gasto general')
        ->toContain('Arqueo / cierre de Caja General')
        ->toContain('Auditoría')
        ->and($script)->toContain('GENERAL_CASH_FUNDING')
        ->toContain('Se reversarán tanto el banco como Caja General')
        ->and($seeder)->toContain('admin.general-cash.index')
        ->toContain('admin.general-cash.expenses.annul')
        ->toContain('admin.general-cash.reports');
});

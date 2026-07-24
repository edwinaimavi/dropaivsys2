<?php

use App\Models\PettyCashExpense;

it('compone la serie y el correlativo del comprobante', function () {
    $expense = new PettyCashExpense([
        'document_series' => 'F001',
        'document_correlative' => '000123',
    ]);

    expect($expense->document_full_number)->toBe('F001-000123');
});

it('muestra una sola parte cuando la otra no existe', function () {
    expect((new PettyCashExpense(['document_series' => 'F001']))->document_full_number)->toBe('F001')
        ->and((new PettyCashExpense(['document_correlative' => '000123']))->document_full_number)->toBe('000123');
});

it('mantiene compatibilidad con el numero de comprobante anterior', function () {
    $expense = new PettyCashExpense(['document_number' => 'B001-000456']);

    expect($expense->document_full_number)->toBe('B001-000456');
});

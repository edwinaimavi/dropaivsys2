<?php

use App\Models\PettyCashBox;
use App\Services\PettyCashCalculator;

it('calcula el saldo y pendiente sin reposiciones', function () {
    expect(PettyCashBox::calculateBalances(1500, 200, 0))->toBe([
        'opening_amount' => 1500.0,
        'current_balance' => 1300.0,
        'pending_replenishment' => 200.0,
    ]);
});

it('calcula una reposicion parcial', function () {
    expect(PettyCashBox::calculateBalances(1500, 200, 100))->toBe([
        'opening_amount' => 1500.0,
        'current_balance' => 1400.0,
        'pending_replenishment' => 100.0,
    ]);
});

it('suma el vuelto retornado como ingreso sin crear un segundo egreso', function () {
    $calculator = new PettyCashCalculator();

    expect($calculator->calculateValues(2000, 2000, 100, 0, 10))->toBe([
        'approved_amount' => 2000.0,
        'initial_fund' => 2000.0,
        'total_expenses' => 100.0,
        'total_replenished' => 0.0,
        'current_balance' => 1910.0,
        'pending_replenishment' => 90.0,
    ]);
});

it('restaura el saldo y permite registrar una reposición excepcional superior', function () {
    expect(PettyCashBox::calculateBalances(1500, 200, 200))->toBe([
        'opening_amount' => 1500.0,
        'current_balance' => 1500.0,
        'pending_replenishment' => 0.0,
    ]);

    expect(PettyCashBox::calculateBalances(1500, 200, 250)['current_balance'])->toBe(1550.0);
});

it('calcula exactamente el caso real del fondo fijo', function () {
    $calculator = new PettyCashCalculator();
    $expenses = [200, 350, 125, 215, 120, 65, 35, 30, 25, 60, 35, 215, 15, 10, 5, 25, 45, 85, 300];

    expect(array_sum($expenses))->toBe(1960)
        ->and($calculator->calculateValues(2000, 2000, array_sum($expenses), 0))->toBe([
            'approved_amount' => 2000.0,
            'initial_fund' => 2000.0,
            'total_expenses' => 1960.0,
            'total_replenished' => 0.0,
            'current_balance' => 40.0,
            'pending_replenishment' => 1960.0,
        ])
        ->and($calculator->calculateValues(2000, 2000, array_sum($expenses), 1960))->toBe([
            'approved_amount' => 2000.0,
            'initial_fund' => 2000.0,
            'total_expenses' => 1960.0,
            'total_replenished' => 1960.0,
            'current_balance' => 2000.0,
            'pending_replenishment' => 0.0,
        ]);
});

it('calcula la primera apertura y el complemento de un periodo anterior', function () {
    $calculator = new PettyCashCalculator();

    expect($calculator->opening(2000))->toBe([
        'available_balance' => 0.0,
        'fund_to_replenish' => 0.0,
        'initial_fund' => 2000.0,
    ])->and($calculator->opening(2000, 200))->toBe([
        'available_balance' => 200.0,
        'fund_to_replenish' => 1800.0,
        'initial_fund' => 2000.0,
    ]);
});

it('incluye el saldo anterior en el fondo disponible inicial', function () {
    expect(PettyCashBox::calculateBalances(1500, 0, 0, 300))->toBe([
        'opening_amount' => 1800.0,
        'current_balance' => 1800.0,
        'pending_replenishment' => 0.0,
    ]);

    expect(PettyCashBox::calculateBalances(1500, 200, 0, 300))->toBe([
        'opening_amount' => 1800.0,
        'current_balance' => 1600.0,
        'pending_replenishment' => 200.0,
    ]);
});

it('permite calcular la apertura solo con saldo anterior', function () {
    expect(PettyCashBox::calculateBalances(0, 0, 0, 1500))->toBe([
        'opening_amount' => 1500.0,
        'current_balance' => 1500.0,
        'pending_replenishment' => 0.0,
    ]);
});

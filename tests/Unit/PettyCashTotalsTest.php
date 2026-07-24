<?php

use App\Models\PettyCashBox;

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

it('restaura el saldo sin superar el fondo aprobado', function () {
    expect(PettyCashBox::calculateBalances(1500, 200, 200))->toBe([
        'opening_amount' => 1500.0,
        'current_balance' => 1500.0,
        'pending_replenishment' => 0.0,
    ]);

    expect(PettyCashBox::calculateBalances(1500, 200, 250)['current_balance'])->toBe(1500.0);
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

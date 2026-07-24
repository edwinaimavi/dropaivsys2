<?php

use App\Models\PettyCashBox;

it('calcula el saldo y pendiente sin reposiciones', function () {
    expect(PettyCashBox::calculateBalances(1500, 200, 0))->toBe([
        'current_balance' => 1300.0,
        'pending_replenishment' => 200.0,
    ]);
});

it('calcula una reposicion parcial', function () {
    expect(PettyCashBox::calculateBalances(1500, 200, 100))->toBe([
        'current_balance' => 1400.0,
        'pending_replenishment' => 100.0,
    ]);
});

it('restaura el saldo sin superar el fondo aprobado', function () {
    expect(PettyCashBox::calculateBalances(1500, 200, 200))->toBe([
        'current_balance' => 1500.0,
        'pending_replenishment' => 0.0,
    ]);

    expect(PettyCashBox::calculateBalances(1500, 200, 250)['current_balance'])->toBe(1500.0);
});

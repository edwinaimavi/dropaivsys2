<?php

use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Models\WarehouseEntryItem;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

function distributeWarehouseExpense(array $data, array $items, string $method, float $amount): array
{
    $reflection = new ReflectionMethod(WarehouseEntryController::class, 'expenseAllocations');
    return $reflection->invoke(new WarehouseEntryController(), $data, collect($items), $method, $amount, 0);
}

function prepareWarehouseExpense(array $data): array
{
    $reflection = new ReflectionMethod(WarehouseEntryController::class, 'prepareLinkedExpense');
    return $reflection->invoke(new WarehouseEntryController(), $data, 0);
}

it('mantiene informativo un costo incluido en la compra', function () {
    $expense = prepareWarehouseExpense([
        'cost_origin' => 'included_in_purchase_price',
        'amount' => 250,
        'affects_inventory_cost' => true,
        'distribution_method' => 'quantity',
    ]);

    expect($expense['amount'])->toBe(0)
        ->and($expense['affects_inventory_cost'])->toBeFalse()
        ->and($expense['distribution_method'])->toBeNull()
        ->and($expense['description'])->toContain('incluido en la compra');
});

it('exige importe positivo cuando el costo no está incluido en la compra', function () {
    expect(fn () => prepareWarehouseExpense([
        'cost_origin' => 'third_party',
        'amount' => 0,
        'affects_inventory_cost' => false,
    ]))->toThrow(ValidationException::class, 'El importe debe ser mayor a 0');
});

it('distribuye exactamente por cantidad incluyendo el ajuste de centavos', function () {
    $items = [new WarehouseEntryItem(['quantity' => 1, 'line_total' => 10]), new WarehouseEntryItem(['quantity' => 2, 'line_total' => 20])];
    $distribution = distributeWarehouseExpense([], $items, 'quantity', 100);

    expect($distribution)->toBe([33.33, 66.67])
        ->and(array_sum($distribution))->toBe(100.0);
});

it('distribuye por valor de los artículos', function () {
    $items = [new WarehouseEntryItem(['quantity' => 5, 'line_total' => 75]), new WarehouseEntryItem(['quantity' => 1, 'line_total' => 25])];
    expect(distributeWarehouseExpense([], $items, 'amount', 40))->toEqual([30.0, 10.0]);
});

it('bloquea una distribución manual cuya suma no coincide con el importe', function () {
    $items = [new WarehouseEntryItem(['quantity' => 1]), new WarehouseEntryItem(['quantity' => 1])];
    expect(fn () => distributeWarehouseExpense(['distributions' => [
        ['item_index' => 0, 'distributed_amount' => 30],
        ['item_index' => 1, 'distributed_amount' => 20],
    ]], $items, 'manual', 100))->toThrow(ValidationException::class, 'La distribución del gasto debe coincidir con el importe total.');
});

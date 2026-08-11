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

function normalizeWarehouseExpense(array $data): array
{
    $reflection = new ReflectionMethod(WarehouseEntryController::class, 'normalizeLinkedExpenseFields');
    return $reflection->invoke(new WarehouseEntryController(), $data);
}

it('mapea automáticamente los campos técnicos desde el tipo de costo visible', function (string $type, array $expected) {
    $expense = normalizeWarehouseExpense(['expense_type' => $type, 'amount' => 60]);

    expect($expense)->toMatchArray($expected)
        ->and($expense['affects_inventory_cost'])->toBeTrue()
        ->and($expense['distribution_method'])->toBe('quantity')
        ->and($expense['distributed_amount'])->toBe(60.0);
})->with([
    'flete de agencia' => ['agency_freight', ['expense_type' => 'agency_freight', 'expense_category' => 'freight_transport', 'cost_origin' => 'third_party']],
    'recojo o traslado' => ['pickup_transfer', ['expense_type' => 'pickup_transfer', 'expense_category' => 'freight_transport', 'cost_origin' => 'third_party']],
    'otros gastos' => ['other', ['expense_type' => 'other', 'expense_category' => 'other_expense', 'cost_origin' => 'third_party']],
    'alias técnico de flete' => ['flete_agencia', ['expense_type' => 'agency_freight', 'expense_category' => 'freight_transport', 'cost_origin' => 'third_party']],
]);

it('usa cero distribuido para un costo informativo sin distributed_amount', function () {
    $expense = normalizeWarehouseExpense([
        'expense_type' => 'legacy_informative',
        'amount' => 60,
        'affects_inventory_cost' => false,
    ]);

    expect($expense['distributed_amount'])->toBe(0.0)
        ->and($expense['affects_inventory_cost'])->toBeFalse();
});

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

it('exige importe positivo cuando el costo afecta inventario', function () {
    expect(fn () => prepareWarehouseExpense([
        'cost_origin' => 'third_party',
        'amount' => 0,
        'affects_inventory_cost' => true,
    ]))->toThrow(ValidationException::class, 'Ingrese un importe válido');
});

it('rechaza importe cero incluso cuando el gasto es informativo', function () {
    expect(fn () => prepareWarehouseExpense([
        'cost_origin' => 'third_party',
        'amount' => 0,
        'affects_inventory_cost' => false,
        'description' => 'TRASLADO ASUMIDO SIN COSTO ADICIONAL',
    ]))->toThrow(ValidationException::class, 'El importe debe ser mayor a 0');
});

it('rechaza IGV para recibos y costos sin comprobante', function (string $documentType) {
    expect(fn () => prepareWarehouseExpense([
        'cost_origin' => 'third_party',
        'amount' => 105,
        'affects_inventory_cost' => true,
        'affects_igv' => true,
        'document_type' => $documentType,
    ]))->toThrow(ValidationException::class, 'no generan IGV aprovechable para el análisis');
})->with(['RECIBO_HONORARIOS', 'RECIBO_INTERNO', 'RECIBO', 'SIN_COMPROBANTE']);

it('permite marcar factura o boleta como afecta a IGV', function (string $documentType) {
    $expense = prepareWarehouseExpense([
        'cost_origin' => 'third_party',
        'amount' => 118,
        'affects_inventory_cost' => true,
        'affects_igv' => true,
        'document_type' => $documentType,
    ]);

    expect($expense['affects_igv'])->toBeTrue();
})->with(['FACTURA', 'BOLETA']);

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

<?php

use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDocument;

it('desglosa un importe afecto a IGV incluido en el total', function () {
    expect(WarehouseEntryExpense::taxBreakdown(118.00, true))->toBe([
        'affects_igv' => true,
        'igv_rate' => 18.0,
        'taxable_amount' => 100.0,
        'igv_amount' => 18.0,
        'total_amount' => 118.0,
    ]);
});

it('mantiene completo como base un importe sin IGV', function () {
    expect(WarehouseEntryExpense::taxBreakdown(118.00, false))->toBe([
        'affects_igv' => false,
        'igv_rate' => 0.0,
        'taxable_amount' => 118.0,
        'igv_amount' => 0.0,
        'total_amount' => 118.0,
    ]);
});

it('solo admite factura y boleta para registrar IGV', function (string $type, bool $expected) {
    expect(WarehouseEntryExpense::supportsIgv($type))->toBe($expected);
})->with([
    ['FACTURA', true],
    ['boleta', true],
    ['RECIBO_HONORARIOS', false],
    ['RECIBO_INTERNO', false],
    ['RECIBO', false],
    ['SIN_COMPROBANTE', false],
]);

it('clasifica documentos oficiales y normaliza recibos históricos', function (string $type, string $normalized, bool $official) {
    expect(WarehouseEntryExpense::normalizeDocumentType($type))->toBe($normalized)
        ->and(WarehouseEntryExpense::isOfficialDocument($type))->toBe($official);
})->with([
    ['FACTURA', 'FACTURA', true],
    ['BOLETA', 'BOLETA', true],
    ['RECIBO_HONORARIOS', 'RECIBO_HONORARIOS', true],
    ['RECIBO_INTERNO', 'RECIBO_INTERNO', false],
    ['RECIBO', 'RECIBO_INTERNO', false],
    ['SIN_COMPROBANTE', 'SIN_COMPROBANTE', false],
]);

it('clasifica adjuntos históricos como factura y conserva la constancia de pago', function (mixed $type, string $expected) {
    expect(WarehouseEntryExpenseDocument::normalizeType($type))->toBe($expected);
})->with([
    [null, 'invoice'],
    ['FACTURA', 'invoice'],
    ['invoice', 'invoice'],
    ['payment_proof', 'payment_proof'],
]);

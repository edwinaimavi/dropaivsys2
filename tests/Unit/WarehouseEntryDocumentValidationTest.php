<?php

use App\Http\Controllers\Admin\WarehouseEntryController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

function warehouseEntryDocumentRules(Request $request): array
{
    $method = new ReflectionMethod(WarehouseEntryController::class, 'newDocumentUploadRules');

    return $method->invoke(new WarehouseEntryController(), $request);
}

it('valida únicamente documentos generales que contienen un archivo nuevo', function () {
    $request = Request::create('/', 'POST', [
        'warehouse_entry_documents' => [
            ['id' => 15, 'file' => 'factura-anterior.pdf', 'original_name' => 'factura-anterior.pdf'],
            ['type' => 'purchase_invoice', 'description' => 'Nuevo comprobante'],
            ['type' => '', 'description' => 'Fila vacía'],
        ],
    ]);
    $request->files->set('warehouse_entry_documents', [
        1 => ['file' => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf')],
    ]);

    $rules = warehouseEntryDocumentRules($request);

    expect($rules)->toHaveKey('warehouse_entry_documents.1.file')
        ->and($rules)->not->toHaveKey('warehouse_entry_documents.0.file')
        ->and($request->input('warehouse_entry_documents'))->toHaveCount(1)
        ->and($request->input('warehouse_entry_documents.1.type'))->toBe('purchase_invoice');
});

it('aplica los mismos formatos seguros a documentos nuevos por lote', function () {
    $request = Request::create('/', 'POST', [
        'warehouse_entry_lot_documents' => [
            ['item_index' => 0, 'lot_key' => 'lot-1', 'type' => 'other'],
        ],
    ]);
    $request->files->set('warehouse_entry_lot_documents', [
        ['file' => UploadedFile::fake()->create('ficha.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
    ]);

    $rules = warehouseEntryDocumentRules($request);

    expect($rules['warehouse_entry_lot_documents.0.file'])
        ->toContain('nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240');
});

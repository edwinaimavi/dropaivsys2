<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ElectronicInvoiceSeries;
use App\Models\SunatCatalogItem;
use Illuminate\Database\Seeder;

class SunatCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['document_type', '01', 'FACTURA', 'Factura'],
            ['document_type', '03', 'BOLETA DE VENTA', 'Boleta'],
            ['document_type', '07', 'NOTA DE CREDITO', 'Nota credito'],
            ['document_type', '08', 'NOTA DE DEBITO', 'Nota debito'],
            ['identity_document_type', '1', 'DNI', 'DNI'],
            ['identity_document_type', '6', 'RUC', 'RUC'],
            ['operation_type', '0101', 'VENTA INTERNA', 'Venta interna'],
            ['tax_affectation', '10', 'GRAVADO - OPERACION ONEROSA', 'Gravado'],
            ['tax_affectation', '20', 'EXONERADO - OPERACION ONEROSA', 'Exonerado'],
            ['tax_affectation', '30', 'INAFECTO - OPERACION ONEROSA', 'Inafecto'],
            ['tax', '1000', 'IGV', 'IGV'],
            ['tax', '9997', 'EXO', 'EXO'],
            ['tax', '9998', 'INA', 'INA'],
            ['currency', 'PEN', 'SOLES', 'PEN'],
            ['currency', 'USD', 'DOLARES', 'USD'],
            ['unit', 'NIU', 'UNIDAD', 'Unidad'],
            ['unit', 'ZZ', 'SERVICIO', 'Servicio'],
            ['unit', 'KG', 'KILOGRAMO', 'Kilogramo'],
            ['unit', 'G', 'GRAMO', 'Gramo'],
            ['unit', 'L', 'LITRO', 'Litro'],
        ];

        foreach ($items as [$catalogCode, $itemCode, $description, $shortName]) {
            SunatCatalogItem::updateOrCreate(
                [
                    'catalog_code' => $catalogCode,
                    'item_code' => $itemCode,
                ],
                [
                    'description' => $description,
                    'short_name' => $shortName,
                    'status' => 'ACTIVE',
                ]
            );
        }

        Company::query()->get()->each(function (Company $company) {
            foreach ([['01', 'F001', 'Serie facturas beta'], ['03', 'B001', 'Serie boletas beta']] as [$type, $serie, $description]) {
                ElectronicInvoiceSeries::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'document_type' => $type,
                        'serie' => $serie,
                        'environment' => 'beta',
                    ],
                    [
                        'current_number' => 0,
                        'next_number' => 1,
                        'description' => $description,
                        'is_default' => true,
                        'status' => 'ACTIVE',
                    ]
                );
            }
        });
    }
}

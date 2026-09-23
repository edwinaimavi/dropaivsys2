<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ElectronicInvoiceSeries;
use Illuminate\Database\Seeder;

class ElectronicInvoiceSeriesSeeder extends Seeder
{
    public function run(): void
    {
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

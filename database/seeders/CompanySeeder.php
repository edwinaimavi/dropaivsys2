<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(
            [
                'ruc' => '20606391367'
            ],
            [
                'business_name' => 'DROPAIV S.A.C.',
                'trade_name' => 'DROGUERIA DROPAIV',
                'email' => 'dropaiv@gmail.com',
                'phone' => '975321222',
                'address' => 'AV. EL EJERCITO CDRA. 2 NRO. S/N (FRENTE A CENTRO ESTETICA ORAL) SAN MARTIN - SAN MARTIN - TARAPOTO',
                'logo' => null,
                'status' => true,
            ]
        );
    }
}

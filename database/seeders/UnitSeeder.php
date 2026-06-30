<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $units = [
            ['abbreviation' => 'KG',     'description' => 'KILOGRAMO'],
            ['abbreviation' => 'G',      'description' => 'GRAMO'],
            ['abbreviation' => 'MG',     'description' => 'MILIGRAMO'],
            ['abbreviation' => 'MCG',    'description' => 'MICROGRAMO'],
            ['abbreviation' => 'MILLAR', 'description' => 'MILLAR'],
            ['abbreviation' => 'UND',    'description' => 'UNIDAD'],
            ['abbreviation' => 'BL',     'description' => 'BOLSA'],
            ['abbreviation' => 'ML',     'description' => 'MILILITRO'],
            ['abbreviation' => 'CM3',    'description' => 'CENTIMETROS CUBICOS'],
            ['abbreviation' => 'CJ',     'description' => 'CAJA'],
            ['abbreviation' => 'LT',     'description' => 'LITRO'],
            ['abbreviation' => 'BLD',    'description' => 'BALDE'],
            ['abbreviation' => 'CIENTO', 'description' => 'CIENTO'],
            ['abbreviation' => 'TAB',    'description' => 'TABLETA'],
            ['abbreviation' => 'CAP',    'description' => 'CAPSULA'],
            ['abbreviation' => 'AMP',    'description' => 'AMPOLLA'],
            ['abbreviation' => 'FR',     'description' => 'FRASCO'],
            ['abbreviation' => 'DET',    'description' => 'DETERMINACION'],
            ['abbreviation' => 'PBA',    'description' => 'PRUEBA'],
            ['abbreviation' => 'ROL',    'description' => 'ROLLO'],
            ['abbreviation' => 'HJ',     'description' => 'HOJA'],
            ['abbreviation' => 'PQT',    'description' => 'PAQUETE'],
            ['abbreviation' => 'SOB',    'description' => 'SOBRE'],
            ['abbreviation' => 'GL',     'description' => 'GALON'],
            ['abbreviation' => 'M',      'description' => 'METRO'],
            ['abbreviation' => 'TU',     'description' => 'TUBO'],
            ['abbreviation' => 'KIT',    'description' => 'KIT'],
            ['abbreviation' => 'PAR',    'description' => 'PAR'],
        ];

        foreach ($units as $unit) {
            DB::table('units')->updateOrInsert(
                [
                    'abbreviation' => $unit['abbreviation'],
                ],
                [
                    'description' => $unit['description'],
                    'decimal_quantity' => 1,
                    'observation' => null,
                    'status' => 'ACTIVE',
                    'created_by' => 1,
                    'updated_by' => 1,
                    'deleted_by' => null,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}

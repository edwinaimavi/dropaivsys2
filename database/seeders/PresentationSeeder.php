<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PresentationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $unitIds = DB::table('units')
            ->whereNull('deleted_at')
            ->pluck('id', 'abbreviation')
            ->toArray();

        if (empty($unitIds)) {
            throw new RuntimeException('Primero debes ejecutar UnitSeeder. La tabla units está vacía.');
        }

        $defaultUnitId = $unitIds['UND'] ?? collect($unitIds)->first();

        $descriptions = [
            'BOLSA X 100',
            'CAJA X 250',
            'CAJA X 40',
            'FR X 10',
            'CAJA X 20',
            'BOLSA X 250',
            'TUBO X 90',
            'ROLLO',
            'CAJA X 144',
            'INDIVIDUAL',
            'CAJA X 45',
            'BOLSA X 1000',
            'CAJA X 10',
            'CAJA X 100',
            'CAJA X 50',
            'CAJA X 24',
            'CAJA X 32',
            'FR X 1000',
            'CAJA X 200',
            'CAJA X 300',

            'CAJA X 36',
            'CAJA X 1 KIT',
            'CAJA X 6',
            'BOLSA X 4',
            'BOLSA X 500',
            'CAJA X 30',
            'GL X 3.785',
            'BOLSA X 1',
            'CAJA X 1 JERINGA PRE-LLENADA',
            'VIAL X 1',
            'BLISTER X 1',
            'RACK X 100',
            'PQT X 50',
            'PQT X 100',
            'CAJA X 500',
            'SOB X 1',
            'CAJA X 25',
            'BOLSA X 50',
            'CAJA X 4',
            'CAJA X 12',

            'BOLSA X 20',
            'CAJA X 125',
            'FR X 1',
            'ROLLO X 54',
            'ROLLO X 55',
            'GL X 5',
            'GL X 4',
            'FR X 750',
            'ROLLO X 15',
            'CAJA X 1',
            'KIT 3 JERI. 1.5 + 1 JERI. ACIDO GRAB + 15 PUNTAS',
            'FR X 250',
            'SET X 1',
            'ROLLO X 200',
            'FR X 11',
            'FR X 40',
            'FR X 15',
            'FR X 120',
            'FR X 100',
            'ROLLO X 100',

            'ENVASE X 1 L CC + PEDAL',
            'KIT SEGUN FT',
            'CARTUCHO X 50',
            'FR X 500',
            'BOLSA X 10',
            'FR X 300',
            'FR X 150',
            'SOB X 100',
            'GL X 4500',
            'ENVASE X 1000',
            'CAJA X 120',
            'GL X 5000 + 15 TIRAS POR GL',
            'ROLLO X 26',
            'CAJA X 80',
            'CAJA X 104',
            'FR X 450 G',
            'FR X 5',
            'KIT X 100',
            'KIT X 30',
            'TUBO X 100',

            'BOLSA X 5',
            'SET X 9',
            'CAJA X 5',
            'FR X 110',
            'SACHET X 10',
            'TUBO X 30',
            'FR X 50',
            'TUBO X 15',
            'ROLLO X 50',
            'SOB X 5',
            'FR X 30',
            'TUBO X 6',
            'KIT X 1',
            'KIT X 12',
            'FR X 14',
            'FR X 450',
            'PQT X 20',
            'FR X 180',
            'FRASCO X 45',
            'TUBO X90 GR',

            'X2 GR. - KIT X2',
            'CAJA X 2 FRASCOS',
            'X 2GR.',
            'X200',
            '1 PIEZA',
            'CAJA X 15',
            'JERINGA X2 GR.',
            'FRASCO X 200',
            'FRASCO X 1 L.',
            'FRASCO X 100 ML.',
            'CAJA X 50 UND.',
            'SET X 7',
            'JERINGA X 2ML',
            'KIT X 2',
            'KIT X JERINGAS DE 1,5gr y 1,2 ml GRABADO ÁCIDO +',
            'FR X 400',
            'BARRA X 75',
            'CAJA X 30 DET.',
            'CAJA X 10 AMP',

            'FR X 15 ML',
            'FR X 1000 ML',
            'TUBO X 20 G',
            'CAJA X 100 TB',
            'GALON X 3785 ML',
            'SOBRE X 4 UND',
            'PACK X 6 COLORES',
            'PACK X 3 UND',
            'PAR',
        ];

        foreach ($descriptions as $description) {
            $description = trim(mb_strtoupper($description, 'UTF-8'));

            $unitAbbreviation = $this->detectUnitAbbreviation($description);
            $quantity = $this->detectQuantity($description);

            DB::table('presentations')->updateOrInsert(
                [
                    'description' => $description,
                ],
                [
                    'quantity' => $quantity,
                    'unit_id' => $unitIds[$unitAbbreviation] ?? $defaultUnitId,
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

    private function detectQuantity(string $description): float
    {
        $description = str_replace(',', '.', $description);

        if (preg_match('/\bX\s*([0-9]+(?:\.[0-9]+)?)/i', $description, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/^([0-9]+)\s+PIEZA/i', $description, $matches)) {
            return (float) $matches[1];
        }

        return 1;
    }

    private function detectUnitAbbreviation(string $description): string
    {
        $description = mb_strtoupper($description, 'UTF-8');

        if (preg_match('/\bML\b|M\.L\.|MILILITRO/i', $description)) {
            return 'ML';
        }

        if (preg_match('/\b[0-9]+(?:[.,][0-9]+)?\s*(GR|G)\b/i', $description)) {
            return 'G';
        }

        if (str_contains($description, 'AMP')) {
            return 'AMP';
        }

        if (str_contains($description, 'DET')) {
            return 'DET';
        }

        if (preg_match('/\b(TB|TAB|TABLETA)\b/i', $description)) {
            return 'TAB';
        }

        if (str_contains($description, 'FRASCO') || preg_match('/^FR\b/i', $description)) {
            return 'FR';
        }

        if (preg_match('/^CAJA\b/i', $description)) {
            return 'CJ';
        }

        if (preg_match('/^BOLSA\b/i', $description)) {
            return 'BL';
        }

        if (preg_match('/^TUBO\b/i', $description)) {
            return 'TU';
        }

        if (preg_match('/^ROLLO\b/i', $description)) {
            return 'ROL';
        }

        if (preg_match('/^GL\b|^GALON\b/i', $description)) {
            return 'GL';
        }

        if (preg_match('/^PQT\b|^PAQUETE\b/i', $description)) {
            return 'PQT';
        }

        if (preg_match('/^SOB\b|^SOBRE\b|^SACHET\b/i', $description)) {
            return 'SOB';
        }

        if (preg_match('/^KIT\b|KIT/i', $description)) {
            return 'KIT';
        }

        if (preg_match('/^PAR\b/i', $description)) {
            return 'PAR';
        }

        return 'UND';
    }
}
<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'code' => 'ALM001',
                'name' => 'Almacén Principal 1',
                'description' => 'Almacén Principal 1',
                'status' => 'ACTIVE',
            ],
            [
                'code' => 'ALM002',
                'name' => 'Almacén Principal 2',
                'description' => 'Almacén Principal 2',
                'status' => 'ACTIVE',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse
            );
        }
    }
}

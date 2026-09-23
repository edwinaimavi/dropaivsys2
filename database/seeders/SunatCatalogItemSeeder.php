<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Alias conservado para compatibilidad con comandos anteriores.
 * La única implementación de carga vive en SunatCatalogSeeder.
 */
class SunatCatalogItemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SunatCatalogSeeder::class);
    }
}

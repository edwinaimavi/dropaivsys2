<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class UbigeoSeeder extends Seeder
{
    public function run(): void
    {
        // Vaciar tabla
        DB::table('ubigeos')->delete();

        // Ruta del SQL
        $path = database_path('sql/ubigeos.sql');

        // Leer SQL
        $sql = File::get($path);

        // Ejecutar SQL
        DB::unprepared($sql);

        $this->command->info('Ubigeos importados correctamente.');
    }
}

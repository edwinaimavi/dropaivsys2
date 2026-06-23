<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Bank;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [

            [
                'description' => 'Banco de Comercio',
                'short_name' => 'BANCOM',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'BCP',
                'short_name' => 'BCP',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Banco Interamericano de Finanzas (BanBif)',
                'short_name' => 'BANBIF',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Banco Pichincha',
                'short_name' => 'PICHINCHA',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'BBVA',
                'short_name' => 'BBVA',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Citibank Perú',
                'short_name' => 'CITIBANK',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Interbank',
                'short_name' => 'INTERBANK',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'MiBanco',
                'short_name' => 'MIBANCO',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Scotiabank Perú',
                'short_name' => 'SCOTIABANK',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Banco GNB Perú',
                'short_name' => 'GNB',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Banco Falabella',
                'short_name' => 'FALABELLA',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Banco Ripley',
                'short_name' => 'RIPLEY',
                'status' => 'ACTIVE'
            ],

            [
                'description' => 'Banco Santander Perú',
                'short_name' => 'SANTANDER',
                'status' => 'ACTIVE'
            ],

        ];

        foreach ($banks as $bank) {

            Bank::create($bank);
        }
    }
}

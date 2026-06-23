<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [

            [
                'code' => 'PEN',
                'description' => 'SOL PERUANO',
                'symbol' => 'S/',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'USD',
                'description' => 'DÓLAR AMERICANO',
                'symbol' => '$',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'EUR',
                'description' => 'EURO',
                'symbol' => '€',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'GBP',
                'description' => 'LIBRA ESTERLINA',
                'symbol' => '£',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'JPY',
                'description' => 'YEN JAPONÉS',
                'symbol' => '¥',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'CHF',
                'description' => 'FRANCO SUIZO',
                'symbol' => 'CHF',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'CAD',
                'description' => 'DÓLAR CANADIENSE',
                'symbol' => 'C$',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'AUD',
                'description' => 'DÓLAR AUSTRALIANO',
                'symbol' => 'A$',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'BRL',
                'description' => 'REAL BRASILEÑO',
                'symbol' => 'R$',
                'status' => 'ACTIVE'
            ],

            [
                'code' => 'MXN',
                'description' => 'PESO MEXICANO',
                'symbol' => '$',
                'status' => 'ACTIVE'
            ],

        ];

        foreach ($currencies as $currency) {

            Currency::create($currency);
        }
    }
}

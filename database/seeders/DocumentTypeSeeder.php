<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documentTypes = [

            [
                'code' => 'DOC001',
                'description' => 'FICHA TÉCNICA',
                'status' => 'ACTIVE',
            ],

            [
                'code' => 'DOC002',
                'description' => 'PROTOCOLO DE ANÁLISIS',
                'status' => 'ACTIVE',
            ],

            [
                'code' => 'DOC003',
                'description' => 'REGISTRO SANITARIO',
                'status' => 'ACTIVE',
            ],

            [
                'code' => 'DOC004',
                'description' => 'ISO O BPM',
                'status' => 'ACTIVE',
            ],

        ];

        foreach ($documentTypes as $documentType) {

            DocumentType::updateOrCreate(

                [
                    'code' => $documentType['code']
                ],

                [
                    'description' => $documentType['description'],
                    'status'      => $documentType['status'],
                ]

            );

        }
    }
}
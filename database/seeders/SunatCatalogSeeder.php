<?php

namespace Database\Seeders;

use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SunatCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $files = glob(database_path('data/sunat/catalogs/*.json')) ?: [];

        $this->seedFiles($files);
    }

    /**
     * @param  array<int, string>  $files
     */
    public function seedFiles(array $files): void
    {
        foreach ($files as $file) {
            $payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $this->validatePayload($payload, $file);

            DB::transaction(function () use ($payload) {
                $catalog = SunatCatalog::updateOrCreate(
                    ['code' => (string) $payload['catalog_code']],
                    [
                        'name' => $payload['catalog_name'],
                        'description' => $payload['description'] ?? null,
                        'source' => $payload['source'] ?? 'sunat_pdf',
                        'is_active' => $payload['is_active'] ?? true,
                    ]
                );

                foreach ($payload['items'] as $item) {
                    SunatCatalogItem::updateOrCreate(
                        [
                            'catalog_code' => $catalog->code,
                            'item_code' => (string) $item['code'],
                        ],
                        [
                            'sunat_catalog_id' => $catalog->id,
                            'description' => $item['description'],
                            'short_name' => $item['short_name'] ?? null,
                            'extra_data' => $item['extra_data'] ?? null,
                            'source' => $item['source'] ?? $catalog->source,
                            'is_official' => $item['is_official'] ?? true,
                            'status' => ($item['is_active'] ?? true) ? 'ACTIVE' : 'INACTIVE',
                        ]
                    );
                }
            });
        }
    }

    private function validatePayload(mixed $payload, string $file): void
    {
        if (! is_array($payload)
            || ! isset($payload['catalog_code'], $payload['catalog_name'], $payload['items'])
            || ! is_string($payload['catalog_code'])
            || ! is_string($payload['catalog_name'])
            || ! is_array($payload['items'])) {
            throw new RuntimeException("El archivo SUNAT [{$file}] no cumple el esquema requerido.");
        }

        foreach ($payload['items'] as $index => $item) {
            if (! is_array($item)
                || ! isset($item['code'], $item['description'])
                || ! is_string($item['code'])
                || ! is_string($item['description'])) {
                throw new RuntimeException("El elemento {$index} del archivo SUNAT [{$file}] no es válido.");
            }
        }
    }
}

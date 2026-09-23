<?php

namespace App\Services;

use App\Models\SunatCatalogItem;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UnitSunatAuditService
{
    private const INVENTORY_TABLES = [
        'warehouse_stocks', 'warehouse_kardex_movements', 'warehouse_entry_items',
        'warehouse_dispatch_items', 'customer_return_items',
    ];

    private const AMBIGUOUS = ['AMP', 'CAP', 'DET', 'FR', 'MCG', 'PBA', 'ROL', 'SOB', 'TAB'];

    private const SAFE_RULES = [
        'UND' => ['NIU', ['UNIDAD']],
        'UN' => ['NIU', ['UNIDAD']],
        'NIU' => ['NIU', ['UNIDAD']],
        'KG' => ['KGM', ['KILOGRAMO']],
        'KGM' => ['KGM', ['KILOGRAMO']],
        'GR' => ['GRM', ['GRAMO']],
        'G' => ['GRM', ['GRAMO']],
        'GRM' => ['GRM', ['GRAMO']],
        'MG' => ['MGM', ['MILIGRAMO']],
        'MGM' => ['MGM', ['MILIGRAMO']],
        'LT' => ['LTR', ['LITRO']],
        'LTR' => ['LTR', ['LITRO']],
        'ML' => ['MLT', ['MILILITRO']],
        'MLT' => ['MLT', ['MILILITRO']],
        'M' => ['MTR', ['METRO']],
        'MTR' => ['MTR', ['METRO']],
        'CC' => ['CMQ', ['CENTIMETRO', 'CUBICO']],
        'CM3' => ['CMQ', ['CENTIMETRO', 'CUBICO']],
        'CMQ' => ['CMQ', ['CENTIMETRO', 'CUBICO']],
        'CAJA' => ['BX', ['CAJA']],
        'CJ' => ['BX', ['CAJA']],
        'BX' => ['BX', ['CAJA']],
        'PAQ' => ['PK', ['PAQUETE']],
        'PQT' => ['PK', ['PAQUETE']],
        'PK' => ['PK', ['PAQUETE']],
        'BOL' => ['BG', ['BOLSA']],
        'BL' => ['BG', ['BOLSA']],
        'BG' => ['BG', ['BOLSA']],
        'BALDE' => ['BJ', ['BALDE']],
        'BLD' => ['BJ', ['BALDE']],
        'BJ' => ['BJ', ['BALDE']],
        'KIT' => ['KT', ['KIT']],
        'KT' => ['KT', ['KIT']],
        'PAR' => ['PR', ['PAR']],
        'PR' => ['PR', ['PAR']],
        'TUBO' => ['TU', ['TUBO']],
        'TU' => ['TU', ['TUBO']],
        'HOJA' => ['LEF', ['HOJA']],
        'HJ' => ['LEF', ['HOJA']],
        'LEF' => ['LEF', ['HOJA']],
        'SERV' => ['ZZ', ['SERVICIO']],
        'ZZ' => ['ZZ', ['SERVICIO']],
        'CIENTO' => ['CEN', ['CIENTO']],
        'MILLAR' => ['MLL', ['MILLAR']],
        'GL' => ['GLL', ['GALON']],
    ];

    public function analyze(): array
    {
        $hasMapping = Schema::hasColumn('units', 'sunat_unit_item_id');
        $columns = ['id', 'abbreviation', 'description'];
        if ($hasMapping) {
            $columns[] = 'sunat_unit_item_id';
        }

        $units = Unit::query()->withTrashed()->select($columns)->orderBy('id')->get();
        if ($hasMapping) {
            $units->load('sunatUnit.catalog');
        }
        $activeItems = SunatCatalogItem::query()
            ->where('catalog_code', SunatUnitPolicy::CATALOG_CODE)
            ->where('status', 'ACTIVE')
            ->whereHas('catalog', fn ($q) => $q->where('code', SunatUnitPolicy::CATALOG_CODE)->where('is_active', true))
            ->get()
            ->keyBy('item_code');
        $articleUnitIds = Schema::hasTable('articles')
            ? DB::table('articles')->whereNotNull('unit_id')->pluck('unit_id')->map(fn ($id) => (int) $id)->unique()
            : collect();
        $inventoryUnitIds = $this->inventoryUnitIds();
        $inventoryArticleUnitIds = Schema::hasTable('articles')
            && Schema::hasColumn('articles', 'item_kind')
            && Schema::hasColumn('articles', 'is_inventory_item')
            ? DB::table('articles')->where('item_kind', 'product')->where('is_inventory_item', true)
                ->whereNotNull('unit_id')->pluck('unit_id')->map(fn ($id) => (int) $id)->unique()
            : collect();

        $result = [
            'migration_applied' => $hasMapping,
            'total_units' => $units->count(),
            'configured' => [],
            'used_by_articles' => $articleUnitIds->count(),
            'used_by_inventory' => $inventoryArticleUnitIds->count(),
            'safe_candidates' => [],
            'ambiguous' => [],
            'conflicts' => [],
            'unused' => [],
        ];

        foreach ($units as $unit) {
            $mappingId = $hasMapping ? $unit->sunat_unit_item_id : null;
            if ($mappingId !== null) {
                $item = $unit->sunatUnit;
                if ($item && $item->catalog_code === '06' && $item->status === 'ACTIVE'
                    && $item->catalog?->code === '06' && $item->catalog->is_active) {
                    $result['configured'][] = $this->reference($unit, $item->item_code, 'Configuración válida');
                } else {
                    $result['conflicts'][] = $this->reference($unit, null, 'La asignación no pertenece a un catálogo SUNAT 06 activo');
                }
            } else {
                $candidate = $this->safeCandidate($unit, $activeItems);
                if ($candidate) {
                    $result['safe_candidates'][] = $candidate;
                } else {
                    $reason = in_array(mb_strtoupper(trim($unit->abbreviation)), self::AMBIGUOUS, true)
                        ? 'Abreviatura declarada ambigua; requiere validación humana'
                        : 'No existe equivalencia inequívoca entre abreviatura, descripción interna y Tabla 06';
                    $result['ambiguous'][] = $this->reference($unit, null, $reason);
                }
            }

            if (! $articleUnitIds->contains((int) $unit->id) && ! $inventoryUnitIds->contains((int) $unit->id)) {
                $result['unused'][] = $this->reference($unit, $mappingId ? $unit->sunatUnit?->item_code : null, 'Sin uso operativo detectado');
            }
        }

        return $result;
    }

    public function applySafeCandidates(): array
    {
        if (! Schema::hasColumn('units', 'sunat_unit_item_id')) {
            return ['migration_applied' => false, 'applied' => [], 'already_configured' => [], 'skipped' => []];
        }

        return DB::transaction(function () {
            $audit = $this->analyze();
            $result = ['migration_applied' => true, 'applied' => [], 'already_configured' => [], 'skipped' => []];

            foreach ($audit['safe_candidates'] as $candidate) {
                $unit = Unit::query()->withTrashed()->whereKey($candidate['id'])->lockForUpdate()->first();
                if (! $unit) {
                    $result['skipped'][] = array_merge($candidate, ['status' => 'NO ENCONTRADA']);
                    continue;
                }
                if ($unit->sunat_unit_item_id !== null) {
                    $result['already_configured'][] = array_merge($candidate, ['status' => 'YA CONFIGURADA']);
                    continue;
                }

                $item = SunatCatalogItem::query()
                    ->where('catalog_code', SunatUnitPolicy::CATALOG_CODE)
                    ->where('item_code', $candidate['suggested_code'])
                    ->where('status', 'ACTIVE')
                    ->whereHas('catalog', fn ($query) => $query
                        ->where('code', SunatUnitPolicy::CATALOG_CODE)
                        ->where('is_active', true))
                    ->first();
                if (! $item) {
                    $result['skipped'][] = array_merge($candidate, ['status' => 'ITEM 06 NO DISPONIBLE']);
                    continue;
                }

                $updated = DB::table('units')
                    ->where('id', $unit->id)
                    ->whereNull('sunat_unit_item_id')
                    ->update(['sunat_unit_item_id' => $item->id]);
                if ($updated !== 1) {
                    $result['already_configured'][] = array_merge($candidate, ['status' => 'YA CONFIGURADA']);
                    continue;
                }

                $result['applied'][] = array_merge($candidate, [
                    'sunat_unit_item_id' => $item->id,
                    'status' => 'APLICADA',
                ]);
            }

            return $result;
        });
    }

    private function safeCandidate(Unit $unit, $activeItems): ?array
    {
        $abbreviation = mb_strtoupper(trim($unit->abbreviation));
        if (in_array($abbreviation, self::AMBIGUOUS, true) || ! isset(self::SAFE_RULES[$abbreviation])) {
            return null;
        }

        [$code, $tokens] = self::SAFE_RULES[$abbreviation];
        $item = $activeItems->get($code);
        $internalDescription = $this->normalize($unit->description);
        $officialDescription = $this->normalize($item?->description);
        if (! $item || collect($tokens)->contains(fn ($token) => ! str_contains($internalDescription, $token))
            || ! collect($tokens)->every(fn ($token) => str_contains($officialDescription, $token))) {
            return null;
        }

        return array_merge(
            $this->reference($unit, $code, 'Coincidencia inequívoca de abreviatura y descripción (confianza alta)'),
            ['sunat_description' => $item->description]
        );
    }

    private function inventoryUnitIds()
    {
        $ids = collect();
        foreach (self::INVENTORY_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'unit_id')) {
                continue;
            }
            $ids = $ids->merge(DB::table($table)->whereNotNull('unit_id')->pluck('unit_id'));
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function normalize(?string $value): string
    {
        return mb_strtoupper(Str::ascii(trim((string) $value)));
    }

    private function reference(Unit $unit, ?string $code, string $reason): array
    {
        return [
            'id' => $unit->id,
            'abbreviation' => $unit->abbreviation,
            'description' => $unit->description,
            'suggested_code' => $code,
            'reason' => $reason,
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\WarehouseEntry;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\WarehouseKardexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RebuildWarehouseKardex extends Command
{
    protected $signature = 'warehouse:kardex-rebuild {--fresh : Limpia Kardex/stock y reprocesa ingresos validos}';

    protected $description = 'Genera movimientos Kardex faltantes desde ingresos de almacen registrados.';

    public function handle(WarehouseKardexService $kardexService): int
    {
        if ($this->option('fresh')) {
            $this->warn('Modo --fresh: se limpiaran warehouse_kardex_movements y warehouse_stocks.');
            DB::transaction(function () {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                WarehouseKardexMovement::query()->truncate();
                WarehouseStock::query()->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            });
        }

        $entries = WarehouseEntry::query()
            ->with([
                'supplier',
                'currency',
                'items.article',
                'items.unit',
                'items.presentation',
                'items.brand',
            ])
            ->whereNull('deleted_at')
            ->where('status', 'registered')
            ->whereNotNull('warehouse_id')
            ->whereHas('items')
            ->orderBy('id')
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            try {
                $kardexService->registerEntryFromWarehouseEntry($entry);
                $processed++;
                $this->line("Procesado {$entry->entry_number}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("Error en {$entry->entry_number}: {$e->getMessage()}");
            }
        }

        $skippedWithoutWarehouse = WarehouseEntry::query()
            ->whereNull('deleted_at')
            ->where('status', 'registered')
            ->whereNull('warehouse_id')
            ->count();

        $this->info("Ingresos procesados: {$processed}");
        $this->info("Ingresos omitidos sin almacen: {$skippedWithoutWarehouse}");
        $this->info("Errores: {$failed}");
        $this->info('Movimientos Kardex: ' . WarehouseKardexMovement::query()->count());
        $this->info('Stocks: ' . WarehouseStock::query()->count());

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

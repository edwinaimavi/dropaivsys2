<?php

namespace App\Services;

use App\Models\WarehouseInventoryPeriodClosure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WarehouseInventoryPeriodClosureService
{
    private static ?bool $tableAvailable = null;


    public function __construct(
        private readonly WarehousePhysicalInventoryRegisterService $physicalRegisterService,
        private readonly WarehouseValuedInventoryRegisterService $valuedRegisterService,
        private readonly WarehouseInventoryReconciliationService $reconciliationService
    ) {}

    public function isClosed(int $companyId, int $warehouseId, mixed $date): bool
    {
        if (! $this->closureTableAvailable()) {
            return false;
        }

        $period = CarbonImmutable::parse($date);
        $latest = $this->latestEvent($companyId, $warehouseId, $period->year, $period->month);

        return $latest?->action === WarehouseInventoryPeriodClosure::ACTION_CLOSE;
    }

    public function assertOpen(int $companyId, int $warehouseId, mixed $date): void
    {
        if (! $this->isClosed($companyId, $warehouseId, $date)) {
            return;
        }

        $period = CarbonImmutable::parse($date);

        throw ValidationException::withMessages([
            'period' => sprintf(
                'El período %02d/%04d está cerrado para la empresa y almacén seleccionados. Reábralo antes de registrar o corregir movimientos.',
                $period->month,
                $period->year
            ),
        ]);
    }

    public function currentState(int $companyId, int $warehouseId, int $year, int $month): ?WarehouseInventoryPeriodClosure
    {
        if (! $this->closureTableAvailable()) {
            return null;
        }

        return $this->latestEvent($companyId, $warehouseId, $year, $month);
    }

    public function close(
        int $companyId,
        int $warehouseId,
        int $year,
        int $month,
        ?string $reason,
        ?int $userId
    ): WarehouseInventoryPeriodClosure {
        if (! $userId) {
            throw ValidationException::withMessages([
                'created_by' => 'No se pudo identificar al usuario responsable del cierre.',
            ]);
        }

        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEndExclusive = $periodStart->addMonth();

        if (now()->lt($periodEndExclusive)) {
            throw ValidationException::withMessages([
                'month' => 'Solo puede cerrar un mes que ya haya finalizado.',
            ]);
        }

        return DB::transaction(function () use ($companyId, $warehouseId, $year, $month, $reason, $userId) {
            $this->lockCompanyWarehouse($companyId, $warehouseId);

            $latest = $this->latestEvent($companyId, $warehouseId, $year, $month, true);
            if ($latest?->action === WarehouseInventoryPeriodClosure::ACTION_CLOSE) {
                throw ValidationException::withMessages([
                    'period' => sprintf('El período %02d/%04d ya se encuentra cerrado.', $month, $year),
                ]);
            }

            $physical = $this->physicalRegisterService->generate($companyId, $year, $month, $warehouseId);
            $valued = $this->valuedRegisterService->generate($companyId, $year, $month, $warehouseId);
            $reconciliation = $this->reconciliationService->audit($companyId, $warehouseId);

            $reconciliationErrors = (int) ($reconciliation['summary']['error_groups'] ?? 0);
            $valuationErrors = (int) ($valued['valuation_inconsistency_count'] ?? 0);

            if ($reconciliationErrors > 0 || $valuationErrors > 0) {
                throw ValidationException::withMessages([
                    'period' => sprintf(
                        'No se puede cerrar %02d/%04d: existen %d grupo(s) con error de conciliación y %d inconsistencia(s) de valorización.',
                        $month,
                        $year,
                        $reconciliationErrors,
                        $valuationErrors
                    ),
                ]);
            }

            $event = WarehouseInventoryPeriodClosure::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'year' => $year,
                'month' => $month,
                'action' => WarehouseInventoryPeriodClosure::ACTION_CLOSE,
                'reason' => filled($reason) ? trim((string) $reason) : null,
                'summary' => [
                    'physical_registers' => count($physical['registers'] ?? []),
                    'physical_incomplete_snapshots' => (int) ($physical['incomplete_snapshot_count'] ?? 0),
                    'valued_registers' => count($valued['registers'] ?? []),
                    'valued_incomplete_snapshots' => (int) ($valued['incomplete_snapshot_count'] ?? 0),
                    'valuation_inconsistencies' => $valuationErrors,
                    'reconciliation' => $reconciliation['summary'] ?? [],
                ],
                'created_by' => $userId,
            ]);

            return $event;
        });
    }

    public function reopen(
        int $companyId,
        int $warehouseId,
        int $year,
        int $month,
        string $reason,
        ?int $userId
    ): WarehouseInventoryPeriodClosure {
        if (! $userId) {
            throw ValidationException::withMessages([
                'created_by' => 'No se pudo identificar al usuario responsable de la reapertura.',
            ]);
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Ingrese un motivo de reapertura de 5 a 2000 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($companyId, $warehouseId, $year, $month, $reason, $userId) {
            $this->lockCompanyWarehouse($companyId, $warehouseId);

            $latest = $this->latestEvent($companyId, $warehouseId, $year, $month, true);
            if (! $latest || $latest->action !== WarehouseInventoryPeriodClosure::ACTION_CLOSE) {
                throw ValidationException::withMessages([
                    'period' => sprintf('El período %02d/%04d no está cerrado.', $month, $year),
                ]);
            }

            $event = WarehouseInventoryPeriodClosure::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'year' => $year,
                'month' => $month,
                'action' => WarehouseInventoryPeriodClosure::ACTION_REOPEN,
                'reason' => $reason,
                'summary' => [
                    'reopened_from_event_id' => $latest->id,
                ],
                'created_by' => $userId,
            ]);

            return $event;
        });
    }

    private function latestEvent(
        int $companyId,
        int $warehouseId,
        int $year,
        int $month,
        bool $lock = false
    ): ?WarehouseInventoryPeriodClosure {
        $query = WarehouseInventoryPeriodClosure::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('year', $year)
            ->where('month', $month)
            ->orderByDesc('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function closureTableAvailable(): bool
    {
        return self::$tableAvailable ??= Schema::hasTable('warehouse_inventory_period_closures');
    }

    private function lockCompanyWarehouse(int $companyId, int $warehouseId): void
    {
        $row = DB::table('company_warehouses')
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El almacén no está habilitado para la empresa seleccionada.',
            ]);
        }
    }
}

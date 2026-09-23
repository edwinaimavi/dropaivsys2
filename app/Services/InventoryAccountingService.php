<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingJournalEntry;
use App\Models\InventoryAccountingSetting;
use App\Models\WarehouseKardexMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAccountingService
{
    public const ROLE_LABELS = [
        'inventory_account_id' => 'Existencias / Inventario',
        'receipt_offset_account_id' => 'Contrapartida de ingresos / compras',
        'cost_of_sales_account_id' => 'Costo de ventas',
        'adjustment_gain_account_id' => 'Ganancia por ajuste de inventario',
        'adjustment_loss_account_id' => 'Pérdida por ajuste de inventario',
        'transfer_clearing_account_id' => 'Cuenta puente de transferencias',
        'opening_offset_account_id' => 'Contrapartida de saldo inicial',
    ];

    private const RECEIPT_OFFSET_OPERATIONS = [
        'warehouse_entry',
        'warehouse_entry_linked_cost',
        'warehouse_entry_cancel',
        'warehouse_entry_linked_cost_cancel',
        'supplier_return',
    ];

    private const COST_OF_SALES_OPERATIONS = [
        'customer_order_dispatch',
        'customer_order_dispatch_cancel',
        'electronic_invoice',
        'electronic_invoice_cancel',
        'customer_return',
        'customer_return_reversal',
    ];

    private const TRANSFER_OPERATIONS = [
        'warehouse_transfer_out',
        'warehouse_transfer_in',
    ];

    public function __construct(
        private readonly WarehouseInventoryPeriodClosureService $periodClosureService
    ) {}

    public function configuration(int $companyId): array
    {
        $accounts = AccountingAccount::query()
            ->where('company_id', $companyId)
            ->where('status', true)
            ->orderBy('code')
            ->get();

        $settings = InventoryAccountingSetting::query()
            ->where('company_id', $companyId)
            ->first();

        return [
            'accounts' => $accounts,
            'settings' => $settings,
            'roles' => self::ROLE_LABELS,
        ];
    }

    public function createAccount(int $companyId, string $code, string $name): AccountingAccount
    {
        $code = trim($code);
        $name = trim($name);

        if ($code === '' || $name === '') {
            throw ValidationException::withMessages([
                'account' => 'El código y el nombre de la cuenta contable son obligatorios.',
            ]);
        }

        $exists = AccountingAccount::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe una cuenta contable con ese código para la empresa.',
            ]);
        }

        return AccountingAccount::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'status' => true,
        ]);
    }

    public function saveSettings(int $companyId, array $values, ?int $userId): InventoryAccountingSetting
    {
        $payload = [];

        foreach (array_keys(self::ROLE_LABELS) as $field) {
            $accountId = isset($values[$field]) && $values[$field] !== ''
                ? (int) $values[$field]
                : null;

            if ($accountId) {
                $valid = AccountingAccount::query()
                    ->whereKey($accountId)
                    ->where('company_id', $companyId)
                    ->where('status', true)
                    ->exists();

                if (! $valid) {
                    throw ValidationException::withMessages([
                        $field => 'La cuenta seleccionada no pertenece a la empresa o está inactiva.',
                    ]);
                }
            }

            $payload[$field] = $accountId;
        }

        $payload['updated_by'] = $userId;

        return InventoryAccountingSetting::query()->updateOrCreate(
            ['company_id' => $companyId],
            $payload
        );
    }

    public function auditPeriod(int $companyId, int $warehouseId, int $year, int $month): array
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEndExclusive = $periodStart->addMonth();

        $movements = $this->periodMovements($companyId, $warehouseId, $periodStart, $periodEndExclusive)->get();
        $settings = InventoryAccountingSetting::query()->where('company_id', $companyId)->first();
        $accounts = AccountingAccount::query()
            ->where('company_id', $companyId)
            ->where('status', true)
            ->get()
            ->keyBy('id');

        $blockers = [];
        $warnings = [];

        if (! $this->periodClosureService->isClosed($companyId, $warehouseId, $periodStart)) {
            $this->push($blockers, 'period_not_closed', 'El período debe estar cerrado antes de generar referencias contables definitivas.');
        }

        if (! $settings) {
            $this->push($blockers, 'accounting_settings_missing', 'La empresa todavía no tiene configuradas las cuentas contables del inventario.');
        }

        $unsupported = [];
        $zeroValued = [];
        $missingRoleFields = [];
        $orphanReferences = [];
        $posted = 0;
        $postable = 0;

        foreach ($movements as $movement) {
            $existingEntry = AccountingJournalEntry::query()
                ->where('warehouse_kardex_movement_id', $movement->id)
                ->first();

            if ($existingEntry) {
                if ($movement->accounting_cuo_snapshot !== $existingEntry->cuo
                    || $movement->accounting_entry_correlative_snapshot !== $existingEntry->correlative) {
                    $orphanReferences[] = $movement->movement_number;
                } else {
                    $posted++;
                }

                continue;
            }

            if ($movement->accounting_cuo_snapshot || $movement->accounting_entry_correlative_snapshot) {
                $orphanReferences[] = $movement->movement_number;
                continue;
            }

            $role = $this->counterpartRole($movement);
            if (! $role) {
                $unsupported[] = $movement->operation_type ?: '(sin operación)';
                continue;
            }

            $amount = $this->movementAmount($movement);
            if ($amount <= 0) {
                $zeroValued[] = $movement->movement_number;
                continue;
            }

            if ($settings) {
                foreach (['inventory_account_id', $role] as $field) {
                    $accountId = (int) ($settings->{$field} ?? 0);
                    if (! $accountId || ! $accounts->has($accountId)) {
                        $missingRoleFields[$field] = self::ROLE_LABELS[$field] ?? $field;
                    }
                }
            }

            $postable++;
        }

        if ($unsupported !== []) {
            $this->push(
                $blockers,
                'unsupported_operation',
                'Existen operaciones del Kardex sin regla contable definida. No se generarán asientos por inferencia.',
                ['operations' => implode(', ', array_values(array_unique($unsupported)))]
            );
        }

        if ($zeroValued !== []) {
            $this->push(
                $blockers,
                'zero_valued_movement',
                'Existen movimientos con efecto físico pero sin importe contable utilizable. Requieren revisión antes de generar un asiento.',
                ['count' => count($zeroValued)]
            );
        }

        if ($missingRoleFields !== []) {
            $this->push(
                $blockers,
                'account_mapping_missing',
                'Faltan cuentas contables necesarias para las operaciones presentes en el período.',
                ['roles' => implode(', ', array_values($missingRoleFields))]
            );
        }

        if ($orphanReferences !== []) {
            $this->push(
                $blockers,
                'accounting_reference_inconsistent',
                'Existen movimientos con referencias contables incompletas o que no coinciden con su asiento. No se sobrescribirán silenciosamente.',
                ['count' => count($orphanReferences)]
            );
        }

        if ($movements->isEmpty()) {
            $this->push($warnings, 'period_without_movements', 'El período no contiene movimientos de Kardex para contabilizar.');
        }

        return [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'year' => $year,
            'month' => $month,
            'movement_count' => $movements->count(),
            'posted_count' => $posted,
            'postable_count' => $postable,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'can_post' => $blockers === [] && $postable > 0,
            'settings' => $settings,
        ];
    }

    public function postPeriod(int $companyId, int $warehouseId, int $year, int $month, ?int $userId): array
    {
        if (! $userId) {
            throw ValidationException::withMessages([
                'created_by' => 'No se pudo identificar al usuario responsable de la contabilización.',
            ]);
        }

        $audit = $this->auditPeriod($companyId, $warehouseId, $year, $month);
        if ($audit['blockers'] !== []) {
            throw ValidationException::withMessages([
                'accounting' => $audit['blockers'][0]['message'],
            ]);
        }

        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEndExclusive = $periodStart->addMonth();

        return DB::transaction(function () use (
            $companyId,
            $warehouseId,
            $periodStart,
            $periodEndExclusive,
            $userId,
            $audit
        ) {
            $settings = InventoryAccountingSetting::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            $movements = $this->periodMovements($companyId, $warehouseId, $periodStart, $periodEndExclusive)
                ->lockForUpdate()
                ->get();

            $created = 0;
            $existing = 0;

            foreach ($movements as $movement) {
                $journal = AccountingJournalEntry::query()
                    ->where('warehouse_kardex_movement_id', $movement->id)
                    ->first();

                if ($journal) {
                    $existing++;
                    continue;
                }

                $role = $this->counterpartRole($movement);
                $amount = $this->movementAmount($movement);

                if (! $role || $amount <= 0) {
                    throw ValidationException::withMessages([
                        'accounting' => 'La situación contable del Kardex cambió después de la auditoría. Vuelva a revisar el período.',
                    ]);
                }

                $inventoryAccountId = (int) $settings->inventory_account_id;
                $counterpartAccountId = (int) $settings->{$role};
                $netInventory = round((float) $movement->total_cost_in - (float) $movement->total_cost_out, 2);
                $cuo = $this->cuoFor($movement);
                $correlative = $this->correlativeFor($movement);

                $journal = AccountingJournalEntry::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'warehouse_kardex_movement_id' => $movement->id,
                    'entry_date' => $movement->movement_date->toDateString(),
                    'cuo' => $cuo,
                    'correlative' => $correlative,
                    'source' => 'inventory_kardex',
                    'description' => trim('Kardex '.$movement->movement_number.' - '.($movement->operation_type ?: 'movimiento de inventario')),
                    'total_debit' => $amount,
                    'total_credit' => $amount,
                    'status' => 'posted',
                    'created_by' => $userId,
                ]);

                if ($netInventory > 0) {
                    $journal->lines()->createMany([
                        [
                            'account_id' => $inventoryAccountId,
                            'debit' => $amount,
                            'credit' => 0,
                            'memo' => 'Efecto de inventario '.$movement->movement_number,
                        ],
                        [
                            'account_id' => $counterpartAccountId,
                            'debit' => 0,
                            'credit' => $amount,
                            'memo' => 'Contrapartida '.$movement->movement_number,
                        ],
                    ]);
                } else {
                    $journal->lines()->createMany([
                        [
                            'account_id' => $counterpartAccountId,
                            'debit' => $amount,
                            'credit' => 0,
                            'memo' => 'Contrapartida '.$movement->movement_number,
                        ],
                        [
                            'account_id' => $inventoryAccountId,
                            'debit' => 0,
                            'credit' => $amount,
                            'memo' => 'Efecto de inventario '.$movement->movement_number,
                        ],
                    ]);
                }

                $movement->forceFill([
                    'accounting_cuo_snapshot' => $cuo,
                    'accounting_entry_correlative_snapshot' => $correlative,
                    'accounting_posted_at' => now(),
                ])->save();

                $created++;
            }

            return [
                'created_count' => $created,
                'existing_count' => $existing,
                'movement_count' => $movements->count(),
                'audit' => $audit,
            ];
        });
    }

    private function periodMovements(
        int $companyId,
        int $warehouseId,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEndExclusive
    ) {
        return WarehouseKardexMovement::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', '!=', 'cancelled')
            ->where('movement_date', '>=', $periodStart)
            ->where('movement_date', '<', $periodEndExclusive)
            ->orderBy('movement_date')
            ->orderBy('id');
    }

    private function counterpartRole(WarehouseKardexMovement $movement): ?string
    {
        $operation = (string) $movement->operation_type;

        if (in_array($operation, self::RECEIPT_OFFSET_OPERATIONS, true)) {
            return 'receipt_offset_account_id';
        }

        if (in_array($operation, self::COST_OF_SALES_OPERATIONS, true)) {
            return 'cost_of_sales_account_id';
        }

        if (in_array($operation, self::TRANSFER_OPERATIONS, true)) {
            return 'transfer_clearing_account_id';
        }

        if ($operation === 'initial_balance') {
            return 'opening_offset_account_id';
        }

        if ($operation === 'manual_adjustment') {
            $netInventory = round((float) $movement->total_cost_in - (float) $movement->total_cost_out, 2);

            return $netInventory > 0
                ? 'adjustment_gain_account_id'
                : ($netInventory < 0 ? 'adjustment_loss_account_id' : null);
        }

        return null;
    }

    private function movementAmount(WarehouseKardexMovement $movement): float
    {
        return abs(round((float) $movement->total_cost_in - (float) $movement->total_cost_out, 2));
    }

    private function cuoFor(WarehouseKardexMovement $movement): string
    {
        return 'KDX-'.str_pad((string) $movement->id, 12, '0', STR_PAD_LEFT);
    }

    private function correlativeFor(WarehouseKardexMovement $movement): string
    {
        $prefix = $movement->operation_type === 'initial_balance' ? 'A' : 'M';

        return $prefix.str_pad((string) $movement->id, 9, '0', STR_PAD_LEFT);
    }

    private function push(array &$target, string $code, string $message, array $meta = []): void
    {
        $target[] = [
            'code' => $code,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}

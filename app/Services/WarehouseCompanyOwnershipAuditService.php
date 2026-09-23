<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItem;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpenseDistribution;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;

class WarehouseCompanyOwnershipAuditService
{
    public function analyze(): array
    {
        $movementSummary = $this->emptySummary();
        $stockSummary = $this->emptySummary();
        $movementResolutions = [];
        $movementCandidatesByStock = [];

        WarehouseKardexMovement::query()
            ->orderBy('id')
            ->each(function (WarehouseKardexMovement $movement) use (
                &$movementSummary,
                &$movementResolutions,
                &$movementCandidatesByStock
            ) {
                $movementSummary['analyzed']++;
                $candidates = $this->movementCompanyCandidates($movement);
                $classification = $this->classify($movement->company_id, $candidates);
                $movementSummary[$classification['status']]++;

                if ($classification['resolved_company_id']) {
                    $movementCandidatesByStock[(int) $movement->warehouse_stock_id][] =
                        $classification['resolved_company_id'];
                }

                if ($classification['status'] === 'resolvable') {
                    $movementResolutions[$movement->id] = $classification['resolved_company_id'];
                }

                if (in_array($classification['status'], ['ambiguous', 'conflicts', 'without_source'], true)) {
                    $movementSummary['details'][] = [
                        'id' => $movement->id,
                        'movement_number' => $movement->movement_number,
                        'status' => $classification['status'],
                        'current_company_id' => $movement->company_id,
                        'candidate_company_ids' => $candidates,
                    ];
                }
            });

        $stockResolutions = [];
        WarehouseStock::withTrashed()
            ->orderBy('id')
            ->each(function (WarehouseStock $stock) use (
                &$stockSummary,
                &$stockResolutions,
                $movementCandidatesByStock
            ) {
                $stockSummary['analyzed']++;
                $candidates = collect($movementCandidatesByStock[$stock->id] ?? [])
                    ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
                $classification = $this->classify($stock->company_id, $candidates);
                if ($classification['status'] === 'ambiguous' && count($candidates) > 1) {
                    $classification['status'] = 'conflicts';
                }
                $stockSummary[$classification['status']]++;

                if ($classification['status'] === 'resolvable') {
                    $stockResolutions[$stock->id] = $classification['resolved_company_id'];
                }

                if (in_array($classification['status'], ['ambiguous', 'conflicts', 'without_source'], true)) {
                    $stockSummary['details'][] = [
                        'id' => $stock->id,
                        'stock_key' => $stock->stock_key,
                        'status' => $classification['status'],
                        'current_company_id' => $stock->company_id,
                        'candidate_company_ids' => $candidates,
                    ];
                }
            });

        $relations = $this->relationCandidates();
        $existingRelations = DB::table('company_warehouses')
            ->get(['company_id', 'warehouse_id'])
            ->map(fn ($row) => $row->company_id.'|'.$row->warehouse_id)
            ->all();
        $existingLookup = array_fill_keys($existingRelations, true);
        $creatableRelations = $relations
            ->reject(fn ($row) => isset($existingLookup[$row['company_id'].'|'.$row['warehouse_id']]))
            ->values()->all();

        return [
            'mode' => 'dry-run',
            'movements' => $movementSummary,
            'stocks' => $stockSummary,
            'company_warehouses' => [
                'candidates' => $relations->count(),
                'existing' => $relations->count() - count($creatableRelations),
                'creatable' => count($creatableRelations),
                'rows' => $creatableRelations,
            ],
            'resolutions' => [
                'movements' => $movementResolutions,
                'stocks' => $stockResolutions,
            ],
        ];
    }

    public function apply(): array
    {
        $report = $this->analyze();

        DB::transaction(function () use ($report) {
            foreach ($report['resolutions']['movements'] as $movementId => $companyId) {
                WarehouseKardexMovement::query()
                    ->whereKey($movementId)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            }

            foreach ($report['resolutions']['stocks'] as $stockId => $companyId) {
                WarehouseStock::withTrashed()
                    ->whereKey($stockId)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId]);
            }

            foreach ($report['company_warehouses']['rows'] as $row) {
                DB::table('company_warehouses')->insertOrIgnore([
                    'company_id' => $row['company_id'],
                    'warehouse_id' => $row['warehouse_id'],
                    'sunat_establishment_code' => null,
                    'is_active' => true,
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $report['mode'] = 'apply';
        $report['applied'] = [
            'movements' => count($report['resolutions']['movements']),
            'stocks' => count($report['resolutions']['stocks']),
            'company_warehouses' => count($report['company_warehouses']['rows']),
        ];

        return $report;
    }

    private function movementCompanyCandidates(WarehouseKardexMovement $movement): array
    {
        return collect([
            $this->companyFromReference($movement->source_type, $movement->source_id),
            $this->companyFromReference($movement->source_item_type, $movement->source_item_id),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function companyFromReference(?string $type, mixed $id): ?int
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            WarehouseEntry::class, class_basename(WarehouseEntry::class) =>
                $this->value('warehouse_entries', (int) $id, 'company_id'),
            WarehouseEntryItem::class, class_basename(WarehouseEntryItem::class) =>
                $this->joinedValue('warehouse_entry_items', 'warehouse_entries', 'warehouse_entry_id', (int) $id),
            WarehouseEntryExpenseDistribution::class, class_basename(WarehouseEntryExpenseDistribution::class) =>
                $this->expenseDistributionCompany((int) $id),
            WarehouseDispatch::class, class_basename(WarehouseDispatch::class) =>
                $this->value('warehouse_dispatches', (int) $id, 'company_id'),
            WarehouseDispatchItem::class, class_basename(WarehouseDispatchItem::class) =>
                $this->joinedValue('warehouse_dispatch_items', 'warehouse_dispatches', 'warehouse_dispatch_id', (int) $id),
            CustomerReturn::class, class_basename(CustomerReturn::class) =>
                $this->value('customer_returns', (int) $id, 'company_id'),
            CustomerReturnItem::class, class_basename(CustomerReturnItem::class) =>
                $this->joinedValue('customer_return_items', 'customer_returns', 'customer_return_id', (int) $id),
            ElectronicInvoice::class, class_basename(ElectronicInvoice::class) =>
                $this->value('electronic_invoices', (int) $id, 'company_id'),
            ElectronicInvoiceItem::class, class_basename(ElectronicInvoiceItem::class) =>
                $this->joinedValue('electronic_invoice_items', 'electronic_invoices', 'electronic_invoice_id', (int) $id),
            default => null,
        };
    }

    private function value(string $table, int $id, string $column): ?int
    {
        $value = DB::table($table)->where('id', $id)->value($column);

        return $value ? (int) $value : null;
    }

    private function joinedValue(string $child, string $parent, string $foreignKey, int $id): ?int
    {
        $value = DB::table($child)
            ->join($parent, $parent.'.id', '=', $child.'.'.$foreignKey)
            ->where($child.'.id', $id)
            ->value($parent.'.company_id');

        return $value ? (int) $value : null;
    }

    private function expenseDistributionCompany(int $id): ?int
    {
        $value = DB::table('warehouse_entry_expense_distributions as distributions')
            ->join('warehouse_entry_expenses as expenses', 'expenses.id', '=', 'distributions.warehouse_entry_expense_id')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'expenses.warehouse_entry_id')
            ->where('distributions.id', $id)
            ->value('entries.company_id');

        return $value ? (int) $value : null;
    }

    private function relationCandidates()
    {
        return collect([
            ['table' => 'warehouse_entries', 'company' => 'company_id', 'warehouse' => 'warehouse_id'],
            ['table' => 'warehouse_dispatches', 'company' => 'company_id', 'warehouse' => 'warehouse_id'],
            ['table' => 'customer_returns', 'company' => 'company_id', 'warehouse' => 'warehouse_id'],
        ])->flatMap(function (array $source) {
            return DB::table($source['table'])
                ->whereNotNull($source['company'])
                ->whereNotNull($source['warehouse'])
                ->distinct()
                ->get([$source['company'].' as company_id', $source['warehouse'].' as warehouse_id'])
                ->map(fn ($row) => [
                    'company_id' => (int) $row->company_id,
                    'warehouse_id' => (int) $row->warehouse_id,
                ]);
        })->unique(fn ($row) => $row['company_id'].'|'.$row['warehouse_id'])->values();
    }

    private function classify(mixed $currentCompanyId, array $candidates): array
    {
        $current = $currentCompanyId ? (int) $currentCompanyId : null;

        if (count($candidates) > 1) {
            return ['status' => $current ? 'conflicts' : 'ambiguous', 'resolved_company_id' => null];
        }

        $candidate = $candidates[0] ?? null;
        if (! $candidate) {
            return [
                'status' => $current ? 'already_owned' : 'without_source',
                'resolved_company_id' => $current,
            ];
        }

        if ($current && $current !== $candidate) {
            return ['status' => 'conflicts', 'resolved_company_id' => null];
        }

        return [
            'status' => $current ? 'already_owned' : 'resolvable',
            'resolved_company_id' => $candidate,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'analyzed' => 0,
            'already_owned' => 0,
            'resolvable' => 0,
            'ambiguous' => 0,
            'without_source' => 0,
            'conflicts' => 0,
            'details' => [],
        ];
    }
}

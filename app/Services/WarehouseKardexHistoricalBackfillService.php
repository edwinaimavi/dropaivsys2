<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItem;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class WarehouseKardexHistoricalBackfillService
{
    public const ACCOUNTING_FIELDS = [
        'sunat_establishment_code_snapshot',
        'article_code_snapshot',
        'article_description_snapshot',
        'sunat_existence_type_code_snapshot',
        'existence_catalog_code_snapshot',
        'existence_code_snapshot',
        'sunat_unit_code_snapshot',
        'unit_description_snapshot',
        'valuation_method_code_snapshot',
        'valuation_method_description_snapshot',
    ];

    public const DOCUMENT_OPERATION_FIELDS = [
        'document_date_snapshot',
        'sunat_document_type_code_snapshot',
        'sunat_operation_type_code_snapshot',
    ];

    public const OPTIONAL_DOCUMENT_FIELDS = [
        'document_series',
        'document_number',
    ];

    public const AUDITED_FIELDS = [
        ...self::ACCOUNTING_FIELDS,
        ...self::DOCUMENT_OPERATION_FIELDS,
        ...self::OPTIONAL_DOCUMENT_FIELDS,
    ];

    public function __construct(
        private readonly WarehouseKardexService $kardexService,
        private readonly WarehouseInventoryPeriodClosureService $periodClosureService
    ) {}

    public function audit(bool $applySafe = false): array
    {
        $result = [
            'mode' => $applySafe ? 'APPLY SAFE' : 'DRY-RUN',
            'summary' => [
                'movements_analyzed' => 0,
                'already_complete' => 0,
                'with_missing' => 0,
                'safe' => 0,
                'manual_review' => 0,
                'source_missing' => 0,
                'conflict' => 0,
                'safe_fields' => 0,
                'manual_fields' => 0,
                'missing_source_fields' => 0,
                'applied_fields' => 0,
            ],
            'field_summary' => array_fill_keys(self::AUDITED_FIELDS, [
                'safe' => 0,
                'manual_review' => 0,
                'source_missing' => 0,
            ]),
            'details' => [],
        ];

        WarehouseKardexMovement::query()
            ->orderBy('id')
            ->chunkById(200, function ($movements) use (&$result): void {
                foreach ($movements as $movement) {
                    $this->auditMovement($movement, $result);
                }
            });

        if ($applySafe) {
            $this->applySafe($result);
        }

        return $result;
    }

    private function auditMovement(WarehouseKardexMovement $movement, array &$result): void
    {
        $result['summary']['movements_analyzed']++;
        $context = $this->historicalContext($movement);
        $fields = [...self::ACCOUNTING_FIELDS, 'sunat_operation_type_code_snapshot'];

        if ($context['external_document']) {
            $fields = [...$fields, 'document_date_snapshot', 'sunat_document_type_code_snapshot', ...self::OPTIONAL_DOCUMENT_FIELDS];
        }

        $missing = array_values(array_filter(
            $fields,
            fn (string $field): bool => $movement->getAttribute($field) === null
        ));

        if ($missing === []) {
            $result['summary']['already_complete']++;

            return;
        }

        $result['summary']['with_missing']++;
        $classifications = [];

        foreach ($missing as $field) {
            $proposal = $this->proposal($movement, $field, $context);
            $classifications[] = $proposal['classification'];
            $counter = match ($proposal['classification']) {
                'SAFE' => 'safe',
                'SOURCE_MISSING' => 'source_missing',
                default => 'manual_review',
            };
            $result['field_summary'][$field][$counter]++;
            $result['summary'][match ($counter) {
                'safe' => 'safe_fields',
                'source_missing' => 'missing_source_fields',
                default => 'manual_fields',
            }]++;

            $result['details'][] = [
                'movement_id' => $movement->id,
                'movement_number' => $movement->movement_number,
                'company_id' => $movement->company_id,
                'warehouse_id' => $movement->warehouse_id,
                'article_id' => $movement->article_id,
                'movement_type' => $movement->movement_type,
                'operation_type' => $movement->operation_type,
                'source_type' => $movement->source_type,
                'source_id' => $movement->source_id,
                'field' => $field,
                ...$proposal,
                'expected_updated_at' => $movement->getRawOriginal('updated_at'),
            ];
        }

        if (in_array('SOURCE_MISSING', $classifications, true)) {
            $result['summary']['source_missing']++;
        } elseif (in_array('MANUAL_REVIEW', $classifications, true)) {
            $result['summary']['manual_review']++;
        } else {
            $result['summary']['safe']++;
        }
    }

    private function proposal(WarehouseKardexMovement $movement, string $field, array $context): array
    {
        if ($field === 'sunat_operation_type_code_snapshot') {
            try {
                $value = $this->kardexService->resolveSunatOperationTypeCode(
                    (string) $movement->movement_type,
                    (string) $movement->operation_type
                );

                return $this->safe($value, 'resolver_central_tabla_12', 'Mapeo determinístico desde tipos históricos almacenados en el movimiento.');
            } catch (Throwable) {
                return $this->manual('resolver_central_tabla_12', 'La operación histórica no tiene un mapeo determinístico vigente.');
            }
        }

        $originalValue = $context['original']?->getAttribute($field);
        if ($originalValue !== null) {
            return $this->safe($originalValue, 'movimiento_original', 'Snapshot congelado en el movimiento original relacionado.');
        }

        if ($field === 'sunat_document_type_code_snapshot') {
            try {
                $value = $this->kardexService->resolveSunatDocumentTypeCode($movement->document_type);
                if ($value !== null) {
                    return $this->safe($value, 'resolver_central_tabla_10', 'Mapeo determinístico desde document_type histórico almacenado en el movimiento.');
                }
            } catch (Throwable) {
                return $this->manual('resolver_central_tabla_10', 'El tipo documental histórico no tiene un mapeo determinístico vigente.');
            }
        }

        $sourceValue = $this->sourceValue($field, $context);
        if ($sourceValue !== null) {
            return $this->safe($sourceValue['value'], $sourceValue['source'], $sourceValue['reason']);
        }

        if ($context['required_original_missing'] || $context['source_missing'] || $context['source_item_missing']) {
            return $this->sourceMissing(
                'origen_historico',
                'El origen histórico requerido está declarado, pero no existe o no puede verificarse.'
            );
        }

        return $this->manual(
            'maestro_actual_no_utilizado',
            'No existe evidencia histórica fiable; la FK solo permitiría consultar un maestro actual mutable.'
        );
    }

    private function sourceValue(string $field, array $context): ?array
    {
        $source = $context['source'];
        $item = $context['source_item'];

        if ($field === 'sunat_document_type_code_snapshot') {
            $documentType = match (true) {
                $source instanceof WarehouseEntry => $source->document_type,
                $source instanceof ElectronicInvoice => $source->document_type,
                default => null,
            };

            if ($this->filled($documentType)) {
                try {
                    $code = $this->kardexService->resolveSunatDocumentTypeCode($documentType);
                    if ($code !== null) {
                        return $this->historicalValue(
                            $code,
                            $source instanceof WarehouseEntry
                                ? 'warehouse_entries.document_type+resolver_central_tabla_10'
                                : 'electronic_invoices.document_type+resolver_central_tabla_10'
                        );
                    }
                } catch (Throwable) {
                    return null;
                }
            }
        }

        if ($field === 'document_date_snapshot') {
            if ($source instanceof WarehouseEntry && $source->document_date !== null) {
                return $this->historicalValue($source->document_date->format('Y-m-d'), 'warehouse_entries.document_date');
            }
            if ($source instanceof ElectronicInvoice && $source->issue_date !== null) {
                return $this->historicalValue($source->issue_date->format('Y-m-d'), 'electronic_invoices.issue_date');
            }
        }

        if ($field === 'document_series') {
            if ($source instanceof WarehouseEntry && $this->filled($source->document_series)) {
                return $this->historicalValue($source->document_series, 'warehouse_entries.document_series');
            }
            if ($source instanceof ElectronicInvoice && $this->filled($source->serie)) {
                return $this->historicalValue($source->serie, 'electronic_invoices.serie');
            }
        }

        if ($field === 'document_number') {
            if ($source instanceof WarehouseEntry && $this->filled($source->document_number)) {
                return $this->historicalValue($source->document_number, 'warehouse_entries.document_number');
            }
            if ($source instanceof ElectronicInvoice && $this->filled($source->correlativo)) {
                return $this->historicalValue($source->correlativo, 'electronic_invoices.correlativo');
            }
        }

        if ($item instanceof WarehouseEntryItem) {
            if ($field === 'article_code_snapshot' && $this->filled($item->article_code)) {
                return $this->historicalValue($item->article_code, 'warehouse_entry_items.article_code');
            }
            if ($field === 'article_description_snapshot' && $this->filled($item->billing_name_snapshot)) {
                return $this->historicalValue($item->billing_name_snapshot, 'warehouse_entry_items.billing_name_snapshot');
            }
        }

        if ($item instanceof ElectronicInvoiceItem) {
            $invoiceItemValues = [
                'article_code_snapshot' => ['product_code', $item->product_code],
                'article_description_snapshot' => [
                    $this->filled($item->billing_name) ? 'billing_name' : 'description',
                    $this->filled($item->billing_name) ? $item->billing_name : $item->description,
                ],
                'sunat_unit_code_snapshot' => ['unit_code', $item->unit_code],
                'unit_description_snapshot' => ['unit_name', $item->unit_name],
            ];
            if (isset($invoiceItemValues[$field]) && $this->filled($invoiceItemValues[$field][1])) {
                [$column, $value] = $invoiceItemValues[$field];

                return $this->historicalValue($value, 'electronic_invoice_items.'.$column);
            }
        }

        return null;
    }

    private function historicalContext(WarehouseKardexMovement $movement): array
    {
        $source = $this->findSource($movement->source_type, $movement->source_id);
        $sourceItem = $this->findSourceItem($movement->source_item_type, $movement->source_item_id);
        [$original, $requiresOriginal] = $this->findOriginalMovement($movement, $sourceItem);
        $documentCode = null;
        $documentMappingFailed = false;

        try {
            $documentCode = $this->kardexService->resolveSunatDocumentTypeCode($movement->document_type);
        } catch (Throwable) {
            $documentMappingFailed = true;
        }

        $externalDocument = $documentCode !== null
            || $documentMappingFailed
            || $source instanceof ElectronicInvoice
            || ($source instanceof WarehouseEntry && $this->sourceHasExternalDocument($source))
            || ($original !== null && collect([
                $original->document_date_snapshot,
                $original->sunat_document_type_code_snapshot,
                $original->document_series,
                $original->document_number,
            ])->contains(fn ($value) => $value !== null));

        return [
            'source' => $source,
            'source_item' => $sourceItem,
            'original' => $original,
            'external_document' => $externalDocument,
            'source_missing' => $movement->source_type !== null && $movement->source_id !== null && $source === null,
            'source_item_missing' => $movement->source_item_type !== null
                && $movement->source_item_id !== null
                && $this->isSupportedSourceItemType($movement->source_item_type)
                && $sourceItem === null,
            'required_original_missing' => $requiresOriginal && $original === null,
        ];
    }

    private function findSource(?string $type, mixed $id): ?Model
    {
        if ($type === null || $id === null) {
            return null;
        }

        return match (true) {
            $this->isType($type, WarehouseEntry::class) => WarehouseEntry::withTrashed()->find($id),
            $this->isType($type, WarehouseDispatch::class) => WarehouseDispatch::query()->find($id),
            $this->isType($type, ElectronicInvoice::class) => ElectronicInvoice::withTrashed()->find($id),
            $this->isType($type, CustomerReturn::class) => CustomerReturn::withTrashed()->find($id),
            default => null,
        };
    }

    private function findSourceItem(?string $type, mixed $id): ?Model
    {
        if ($type === null || $id === null) {
            return null;
        }

        return match (true) {
            $this->isType($type, WarehouseEntryItem::class) => WarehouseEntryItem::query()->find($id),
            $this->isType($type, WarehouseDispatchItem::class) => WarehouseDispatchItem::query()->find($id),
            $this->isType($type, ElectronicInvoiceItem::class) => ElectronicInvoiceItem::query()->find($id),
            $this->isType($type, CustomerReturnItem::class) => CustomerReturnItem::query()->find($id),
            default => null,
        };
    }

    private function findOriginalMovement(WarehouseKardexMovement $movement, ?Model $sourceItem): array
    {
        if (preg_match('/^(?:reversal|invoice-exit-reversal|customer-dispatch-reversal):(\d+)$/', (string) $movement->source_key, $matches)) {
            return [WarehouseKardexMovement::query()->find((int) $matches[1]), true];
        }

        if ($movement->operation_type === 'customer_return_reversal') {
            $item = $sourceItem instanceof CustomerReturnItem ? $sourceItem : null;

            return [$item?->kardexMovement, true];
        }

        if ($movement->operation_type === 'customer_return') {
            $item = $sourceItem instanceof CustomerReturnItem ? $sourceItem : null;
            $dispatchItem = $item?->warehouseDispatchItem;

            return [$dispatchItem?->kardexMovement, true];
        }

        if (in_array($movement->operation_type, [
            'warehouse_entry_cancel',
            'warehouse_entry_linked_cost_cancel',
            'electronic_invoice_cancel',
            'customer_order_dispatch_cancel',
        ], true)) {
            return [null, true];
        }

        return [null, false];
    }

    private function sourceHasExternalDocument(WarehouseEntry $entry): bool
    {
        try {
            return $this->kardexService->resolveSunatDocumentTypeCode($entry->document_type) !== null;
        } catch (Throwable) {
            return $this->filled($entry->document_type);
        }
    }

    private function applySafe(array &$result): void
    {
        $grouped = collect($result['details'])
            ->where('classification', 'SAFE')
            ->groupBy('movement_id');

        foreach ($grouped as $movementId => $details) {
            DB::transaction(function () use ($movementId, $details, &$result): void {
                $movement = WarehouseKardexMovement::query()->lockForUpdate()->find($movementId);
                $expectedUpdatedAt = $details->first()['expected_updated_at'];

                if ($movement === null || $movement->getRawOriginal('updated_at') !== $expectedUpdatedAt) {
                    $result['summary']['conflict']++;

                    return;
                }

                $updates = [];
                foreach ($details as $detail) {
                    if ($movement->getAttribute($detail['field']) !== null) {
                        $result['summary']['conflict']++;

                        return;
                    }
                    $updates[$detail['field']] = $detail['proposed_value'];
                }

                if ($updates !== []) {
                    $this->periodClosureService->assertOpen(
                        (int) $movement->company_id,
                        (int) $movement->warehouse_id,
                        $movement->movement_date
                    );
                    WarehouseKardexMovement::query()->whereKey($movement->id)->update($updates);
                    $result['summary']['applied_fields'] += count($updates);
                }
            });
        }
    }

    private function safe(mixed $value, string $source, string $reason): array
    {
        return [
            'classification' => 'SAFE',
            'proposed_value' => $value,
            'source' => $source,
            'reason' => $reason,
        ];
    }

    private function manual(string $source, string $reason): array
    {
        return [
            'classification' => 'MANUAL_REVIEW',
            'proposed_value' => null,
            'source' => $source,
            'reason' => $reason,
        ];
    }

    private function sourceMissing(string $source, string $reason): array
    {
        return [
            'classification' => 'SOURCE_MISSING',
            'proposed_value' => null,
            'source' => $source,
            'reason' => $reason,
        ];
    }

    private function historicalValue(mixed $value, string $source): array
    {
        return [
            'value' => $value,
            'source' => $source,
            'reason' => 'Valor almacenado históricamente en el documento o ítem origen.',
        ];
    }

    private function isType(string $actual, string $expected): bool
    {
        return $actual === $expected || class_basename($actual) === class_basename($expected);
    }

    private function isSupportedSourceItemType(string $type): bool
    {
        return collect([
            WarehouseEntryItem::class,
            WarehouseDispatchItem::class,
            ElectronicInvoiceItem::class,
            CustomerReturnItem::class,
        ])->contains(fn (string $expected): bool => $this->isType($type, $expected));
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}

<?php

namespace App\Services;

use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerReturn;
use App\Models\ElectronicInvoiceItemDispatchAllocation;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseDispatchService
{
    private const SCALE = 4;

    private const MAX_CREATE_ATTEMPTS = 3;

    public function __construct(
        private readonly WarehouseKardexService $kardexService,
        private readonly CustomerPurchaseOrderStatusService $statusService,
        private readonly WarehouseDispatchDocumentService $documentService,
        private readonly CompanyWarehouseService $companyWarehouseService,
        private readonly ArticleInventoryPolicy $articleInventoryPolicy,
        private readonly SunatUnitPolicy $sunatUnitPolicy,
        private readonly ArticleSunatInventoryCatalogPolicy $sunatInventoryCatalogPolicy,
        private readonly WarehouseValuationPoolService $warehouseValuationPoolService
    ) {}

    public function createDraft(
        CustomerPurchaseOrder $order,
        array $data,
        array $documents = []
    ): WarehouseDispatch
    {
        if (blank($data['warehouse_id'] ?? null)) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Seleccione un almacén para registrar la salida.',
            ]);
        }

        $data['idempotency_key'] = $data['idempotency_key'] ?? (string) Str::uuid();
        $confirmedBy = Auth::id();
        if (! $confirmedBy) {
            throw ValidationException::withMessages([
                'confirmed_by' => 'No se pudo identificar al usuario que confirma la salida.',
            ]);
        }

        for ($attempt = 1; $attempt <= self::MAX_CREATE_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($order, $data, $documents, $confirmedBy) {
                    $order = CustomerPurchaseOrder::query()
                        ->whereKey($order->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $order->loadMissing(['customer', 'items.article']);
                    $this->companyWarehouseService->assertEnabled(
                        (int) $order->company_id,
                        (int) $data['warehouse_id']
                    );

                    $existingDispatch = WarehouseDispatch::query()
                        ->where('idempotency_key', $data['idempotency_key'])
                        ->first();
                    if ($existingDispatch) {
                        if ((int) $existingDispatch->customer_purchase_order_id !== (int) $order->id) {
                            throw ValidationException::withMessages([
                                'idempotency_key' => 'La clave de idempotencia ya fue utilizada para otra orden.',
                            ]);
                        }

                        return $existingDispatch->load([
                            'warehouse',
                            'responsibleUser',
                            'creator',
                            'confirmedBy',
                            'items.customerPurchaseOrderItem.article',
                        ]);
                    }

                    if (in_array($order->status, ['cancelled', 'delivered', 'invoiced', 'not_attended'], true)) {
                        throw ValidationException::withMessages([
                            'customer_purchase_order_id' => 'La OC Cliente no admite nuevas salidas en su estado actual.',
                        ]);
                    }

                    $requestItems = collect($data['items'] ?? [])->map(function ($item) {
                        if (! is_array($item)
                            || empty($item['customer_purchase_order_item_id'])
                            || empty($item['warehouse_stock_id'])) {
                            throw ValidationException::withMessages([
                                'items' => 'Cada asignación debe indicar el artículo de la OC Cliente y el lote/stock seleccionado.',
                            ]);
                        }

                        return [
                            'customer_purchase_order_item_id' => (int) $item['customer_purchase_order_item_id'],
                            'warehouse_stock_id' => (int) $item['warehouse_stock_id'],
                            'quantity' => $this->validatedQuantity($item['quantity'] ?? null),
                        ];
                    })->values();
                    if ($requestItems->isEmpty()) {
                        throw ValidationException::withMessages(['items' => 'Registre al menos una cantidad a despachar.']);
                    }

                    $seenAssignments = [];
                    foreach ($requestItems as $row) {
                        $assignmentKey = $row['customer_purchase_order_item_id'].'|'.$row['warehouse_stock_id'];
                        if (isset($seenAssignments[$assignmentKey])) {
                            throw ValidationException::withMessages([
                                'items' => 'El lote/stock seleccionado está repetido para este artículo.',
                            ]);
                        }
                        $seenAssignments[$assignmentKey] = true;
                    }

                    $orderItems = $order->items->where('status', '!=', 'deleted')->keyBy('id');
                    $entered = $this->enteredQuantities($order);
                    $dispatched = $this->dispatchedQuantities($order);

                    foreach ($requestItems->groupBy('customer_purchase_order_item_id') as $itemId => $rows) {
                        $orderItem = $orderItems->get((int) $itemId);
                        if (! $orderItem) {
                            throw ValidationException::withMessages(['items' => 'Uno de los artículos no pertenece a la OC Cliente.']);
                        }

                        $requestedNow = $rows->reduce(
                            fn (string $total, array $row) => bcadd($total, $row['quantity'], self::SCALE),
                            '0.0000'
                        );
                        $requested = $this->decimalQuantity($orderItem->quantity);
                        $alreadyDispatched = $this->decimalQuantity($dispatched->get((int) $itemId, 0));
                        $enteredQuantity = $this->decimalQuantity($entered->get((int) $itemId, 0));
                        $pendingOrder = $this->subtractQuantity($requested, $alreadyDispatched);
                        $availableForOrder = $this->subtractQuantity($enteredQuantity, $alreadyDispatched);

                        if (bccomp($requestedNow, $pendingOrder, self::SCALE) > 0) {
                            throw ValidationException::withMessages([
                                'items' => "La cantidad a despachar de {$orderItem->billing_name_snapshot} supera el pendiente de la OC Cliente.",
                            ]);
                        }
                        if (bccomp($requestedNow, $availableForOrder, self::SCALE) > 0) {
                            throw ValidationException::withMessages([
                                'items' => "La cantidad a despachar de {$orderItem->billing_name_snapshot} supera lo ingresado para esta OC Cliente.",
                            ]);
                        }
                    }

                    $stockIds = $requestItems->pluck('warehouse_stock_id')->unique()->sort()->values();
                    $stocks = WarehouseStock::query()
                        ->where('company_id', $order->company_id)
                        ->whereIn('id', $stockIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($stocks->count() !== $stockIds->count()) {
                        throw ValidationException::withMessages([
                            'items' => 'No hay stock disponible en el almacén seleccionado.',
                        ]);
                    }

                    foreach ($requestItems as $row) {
                        $orderItem = $orderItems->get($row['customer_purchase_order_item_id']);
                        $stock = $stocks->get($row['warehouse_stock_id']);

                        $this->companyWarehouseService->assertStockOwner($stock, (int) $order->company_id);

                        if ((int) $stock->warehouse_id !== (int) $data['warehouse_id']) {
                            throw ValidationException::withMessages(['items' => 'El saldo seleccionado no pertenece al almacén indicado.']);
                        }
                        if ((int) $stock->article_id !== (int) $orderItem->article_id) {
                            throw ValidationException::withMessages(['items' => 'El saldo seleccionado no pertenece al artículo indicado.']);
                        }
                        if ($stock->status !== 'ACTIVE' || bccomp($this->decimalQuantity($stock->current_quantity), '0', self::SCALE) <= 0) {
                            throw ValidationException::withMessages(['items' => 'No hay stock disponible en el almacén seleccionado.']);
                        }
                        $this->articleInventoryPolicy->assertCanParticipateInInventory($orderItem->article, 'items');
                        if ($orderItem->article?->has_batch && blank($stock->lot_number)) {
                            throw ValidationException::withMessages(['items' => "Seleccione un lote para {$orderItem->billing_name_snapshot}."]);
                        }
                        if ($orderItem->article?->has_expiration && blank($stock->expiration_date)) {
                            throw ValidationException::withMessages(['items' => "Seleccione un saldo con vencimiento para {$orderItem->billing_name_snapshot}."]);
                        }
                    }

                    foreach ($requestItems->groupBy('warehouse_stock_id') as $stockId => $rows) {
                        $stock = $stocks->get((int) $stockId);
                        $requestedFromStock = $rows->reduce(
                            fn (string $total, array $row) => bcadd($total, $row['quantity'], self::SCALE),
                            '0.0000'
                        );
                        if (bccomp($requestedFromStock, $this->decimalQuantity($stock->current_quantity), self::SCALE) > 0) {
                            $orderItem = $orderItems->get($rows->first()['customer_purchase_order_item_id']);
                            throw ValidationException::withMessages([
                                'items' => "La cantidad a despachar de {$orderItem->billing_name_snapshot} supera el stock disponible del saldo seleccionado.",
                            ]);
                        }
                    }

                    $dispatch = WarehouseDispatch::create([
                        'company_id' => $order->company_id,
                        'dispatch_number' => $this->generateDispatchNumber(),
                        'idempotency_key' => $data['idempotency_key'],
                        'customer_purchase_order_id' => $order->id,
                        'warehouse_id' => $data['warehouse_id'],
                        'dispatch_date' => $data['dispatch_date'],
                        'responsible_user_id' => $data['responsible_user_id'] ?? $confirmedBy,
                        'destination' => $data['destination'] ?? null,
                        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
                        'observation' => $data['observation'] ?? null,
                        'document_type' => $data['document_type'] ?? null,
                        'document_number' => $data['document_number'] ?? null,
                        'document_path' => $data['document_path'] ?? null,
                        'document_name' => $data['document_name'] ?? null,
                        'document_mime' => $data['document_mime'] ?? null,
                        'status' => WarehouseDispatch::STATUS_DRAFT,
                        'confirmed_at' => null,
                        'confirmed_by' => null,
                        'created_by' => $confirmedBy,
                        'updated_by' => $confirmedBy,
                    ]);

                    foreach ($requestItems as $row) {
                        $orderItem = $orderItems->get($row['customer_purchase_order_item_id']);
                        $stock = $stocks->get($row['warehouse_stock_id']);
                        $quantity = $row['quantity'];

                        $unitCost = round((float) $stock->average_unit_cost, 6);
                        $totalCost = round((float) $quantity * $unitCost, 2);

                        WarehouseDispatchItem::create([
                            'warehouse_dispatch_id' => $dispatch->id,
                            'customer_purchase_order_item_id' => $orderItem->id,
                            'warehouse_stock_id' => $stock->id,
                            'article_id' => $stock->article_id,
                            'unit_id' => $stock->unit_id,
                            'presentation_id' => $stock->presentation_id,
                            'brand_id' => $stock->brand_id,
                            'lot_number' => $stock->lot_number,
                            'expiration_date' => $stock->expiration_date,
                            'quantity' => $quantity,
                            'unit_cost' => $unitCost,
                            'total_cost' => $totalCost,
                            'kardex_movement_id' => null,
                            'status' => WarehouseDispatchItem::STATUS_DRAFT,
                        ]);
                    }

                    if ($documents) {
                        $this->documentService->storeMany($dispatch, $documents, $confirmedBy);
                    }

                    return $dispatch->fresh([
                        'warehouse',
                        'responsibleUser',
                        'creator',
                        'documents',
                        'items.customerPurchaseOrderItem.article',
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isRetriableDispatchCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'dispatch_number' => 'No se pudo reservar un número de salida. Intente nuevamente.',
        ]);
    }

    public function updateDraft(
        WarehouseDispatch $dispatch,
        array $data,
        array $documents = []
    ): WarehouseDispatch {
        return DB::transaction(function () use ($dispatch, $data, $documents) {
            $dispatch = WarehouseDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            if (! $dispatch->isDraft()) {
                throw ValidationException::withMessages([
                    'dispatch' => 'Solo los despachos en borrador pueden modificarse.',
                ]);
            }

            $order = CustomerPurchaseOrder::query()
                ->whereKey($dispatch->customer_purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $dispatch->company_id !== (int) $order->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'La empresa del despacho no coincide con la OC Cliente.',
                ]);
            }
            $order->loadMissing(['customer', 'items.article']);
            $this->companyWarehouseService->assertEnabled(
                (int) $dispatch->company_id,
                (int) $data['warehouse_id']
            );
            $rows = $this->validatedDraftRows($order, $data);

            $dispatch->update([
                'warehouse_id' => $data['warehouse_id'],
                'dispatch_date' => $data['dispatch_date'],
                'responsible_user_id' => $data['responsible_user_id'] ?? Auth::id(),
                'destination' => $data['destination'] ?? null,
                'observation' => $data['observation'] ?? null,
                'document_type' => $data['document_type'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'updated_by' => Auth::id(),
            ]);
            $this->syncDraftItems($dispatch, $rows);

            if ($documents) {
                $this->documentService->storeMany($dispatch, $documents, Auth::id());
            }

            return $this->dispatchResult($dispatch);
        });
    }

    public function confirm(WarehouseDispatch $dispatch): WarehouseDispatch
    {
        $confirmedBy = Auth::id();
        if (! $confirmedBy) {
            throw ValidationException::withMessages([
                'confirmed_by' => 'No se pudo identificar al usuario que confirma la salida.',
            ]);
        }

        return DB::transaction(function () use ($dispatch, $confirmedBy) {
            $dispatch = WarehouseDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            if ($dispatch->isConfirmed()) {
                return $this->dispatchResult($dispatch);
            }
            if (! $dispatch->isDraft()) {
                throw ValidationException::withMessages([
                    'dispatch' => 'Solo los despachos en borrador pueden confirmarse.',
                ]);
            }

            $order = CustomerPurchaseOrder::query()
                ->whereKey($dispatch->customer_purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();
            $order->loadMissing(['customer', 'items.article']);
            if ((int) $dispatch->company_id !== (int) $order->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'La empresa del despacho no coincide con la OC Cliente.',
                ]);
            }
            $this->companyWarehouseService->assertEnabled(
                (int) $dispatch->company_id,
                (int) $dispatch->warehouse_id
            );
            if (in_array($order->status, ['cancelled', 'delivered', 'invoiced', 'not_attended'], true)) {
                throw ValidationException::withMessages([
                    'customer_purchase_order_id' => 'La OC Cliente no admite confirmar esta salida en su estado actual.',
                ]);
            }

            $details = WarehouseDispatchItem::query()
                ->where('warehouse_dispatch_id', $dispatch->id)
                ->orderBy('warehouse_stock_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($details->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'El borrador no tiene artículos para confirmar.',
                ]);
            }
            if ($details->contains(fn (WarehouseDispatchItem $item) =>
                $item->status !== WarehouseDispatchItem::STATUS_DRAFT || $item->kardex_movement_id !== null
            )) {
                throw ValidationException::withMessages([
                    'items' => 'El detalle del borrador no se encuentra en un estado válido para confirmar.',
                ]);
            }

            $orderItems = $order->items->where('status', '!=', 'deleted')->keyBy('id');
            $entered = $this->enteredQuantities($order);
            $dispatched = $this->dispatchedQuantities($order);
            foreach ($details->groupBy('customer_purchase_order_item_id') as $itemId => $itemDetails) {
                $orderItem = $orderItems->get((int) $itemId);
                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los artículos ya no pertenece a la OC Cliente.',
                    ]);
                }
                $quantity = $itemDetails->reduce(
                    fn (string $total, WarehouseDispatchItem $item) => bcadd($total, $this->validatedQuantity($item->quantity), self::SCALE),
                    '0.0000'
                );
                $requested = $this->decimalQuantity($orderItem->quantity);
                $alreadyDispatched = $this->decimalQuantity($dispatched->get((int) $itemId, 0));
                $enteredQuantity = $this->decimalQuantity($entered->get((int) $itemId, 0));
                $pending = $this->subtractQuantity($requested, $alreadyDispatched);
                $available = $this->subtractQuantity($enteredQuantity, $alreadyDispatched);

                if (bccomp($quantity, $pending, self::SCALE) > 0
                    || bccomp($quantity, $available, self::SCALE) > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'La cantidad pendiente de la orden cambió. Actualice el borrador antes de confirmar.',
                    ]);
                }
            }

            $stockIds = $details->pluck('warehouse_stock_id')->unique()->sort()->values();
            $stocks = WarehouseStock::query()
                ->where('company_id', $dispatch->company_id)
                ->whereIn('id', $stockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($stocks->count() !== $stockIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'El stock disponible cambió desde que se creó el borrador.',
                ]);
            }

            foreach ($details as $detail) {
                $orderItem = $orderItems->get((int) $detail->customer_purchase_order_item_id);
                $stock = $stocks->get((int) $detail->warehouse_stock_id);
                $this->companyWarehouseService->assertStockOwner($stock, (int) $dispatch->company_id);
                $stockExpiration = $stock->expiration_date?->format('Y-m-d');
                $detailExpiration = $detail->expiration_date?->format('Y-m-d');

                if ($stock->status !== 'ACTIVE'
                    || (int) $stock->warehouse_id !== (int) $dispatch->warehouse_id
                    || (int) $stock->article_id !== (int) $orderItem->article_id
                    || (string) ($stock->lot_number ?? '') !== (string) ($detail->lot_number ?? '')
                    || $stockExpiration !== $detailExpiration) {
                    throw ValidationException::withMessages([
                        'items' => 'El stock o lote seleccionado cambió desde que se creó el borrador.',
                    ]);
                }
                $this->articleInventoryPolicy->assertCanParticipateInInventory($orderItem->article, 'items');
                $this->articleInventoryPolicy->assertHasSunatExistenceType($orderItem->article, 'items');
                $this->sunatUnitPolicy->codeForArticle($orderItem->article, 'items', true);
                $this->sunatInventoryCatalogPolicy->assertHasInventoryIdentification($orderItem->article, 'items');
                if ($orderItem->article?->has_batch && blank($stock->lot_number)) {
                    throw ValidationException::withMessages(['items' => "Seleccione un lote para {$orderItem->billing_name_snapshot}."]);
                }
                if ($orderItem->article?->has_expiration && blank($stock->expiration_date)) {
                    throw ValidationException::withMessages(['items' => "Seleccione un saldo con vencimiento para {$orderItem->billing_name_snapshot}."]);
                }
            }

            foreach ($details->groupBy('warehouse_stock_id') as $stockId => $stockDetails) {
                $stock = $stocks->get((int) $stockId);
                $quantity = $stockDetails->reduce(
                    fn (string $total, WarehouseDispatchItem $item) => bcadd($total, $this->validatedQuantity($item->quantity), self::SCALE),
                    '0.0000'
                );
                if (bccomp($quantity, $this->decimalQuantity($stock->current_quantity), self::SCALE) > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'El stock disponible cambió desde que se creó el borrador.',
                    ]);
                }
            }

            $valuationPools = [];
            foreach ($stocks->pluck('article_id')->unique()->sort()->values() as $articleId) {
                $valuationPools[(int) $articleId] = $this->warehouseValuationPoolService->lockPool(
                    (int) $dispatch->company_id,
                    (int) $dispatch->warehouse_id,
                    (int) $articleId,
                    $confirmedBy
                );
            }

            foreach ($details as $detail) {
                $stock = $stocks->get((int) $detail->warehouse_stock_id);
                $quantity = $this->validatedQuantity($detail->quantity);
                $legacyUnitCost = round((float) $stock->average_unit_cost, 6);
                $legacyTotalCost = round((float) $quantity * $legacyUnitCost, 2);
                $valuation = $this->warehouseValuationPoolService->removeQuantityAtAverage(
                    $valuationPools[(int) $stock->article_id],
                    $quantity,
                    $confirmedBy
                );
                $valuationPools[(int) $stock->article_id] = $valuation['pool'];
                $unitCost = $valuation['unit_cost'];
                $totalCost = $valuation['total_cost'];
                $newQuantity = bcsub($this->decimalQuantity($stock->current_quantity), $quantity, self::SCALE);
                $newTotalCost = max(round((float) $stock->total_cost - $legacyTotalCost, 2), 0);
                if (bccomp($newQuantity, '0', self::SCALE) <= 0) {
                    $newQuantity = '0.0000';
                    $newTotalCost = 0;
                }
                $averageCost = $this->kardexService->calculateAverageCost($newTotalCost, $newQuantity);

                $stock->update([
                    'current_quantity' => $newQuantity,
                    'total_cost' => $newTotalCost,
                    'average_unit_cost' => $averageCost,
                    'updated_by' => $confirmedBy,
                ]);

                $movement = WarehouseKardexMovement::create([
                    'movement_number' => $this->kardexService->generateMovementNumber(),
                    'company_id' => $dispatch->company_id,
                    'warehouse_stock_id' => $stock->id,
                    'warehouse_id' => $dispatch->warehouse_id,
                    'article_id' => $stock->article_id,
                    'unit_id' => $stock->unit_id,
                    ...$this->kardexService->accountingSnapshots(
                        (int) $dispatch->company_id,
                        (int) $dispatch->warehouse_id,
                        (int) $stock->article_id,
                        $stock->unit_id ? (int) $stock->unit_id : null
                    ),
                    'presentation_id' => $stock->presentation_id,
                    'brand_id' => $stock->brand_id,
                    'lot_number' => $stock->lot_number,
                    'expiration_date' => $stock->expiration_date,
                    'origin' => $stock->origin,
                    'cost_type' => $stock->cost_type,
                    'movement_date' => $dispatch->dispatch_date,
                    'movement_type' => 'exit',
                    'operation_type' => 'customer_order_dispatch',
                    ...$this->kardexService->sunatDocumentOperationSnapshots(
                        'exit',
                        'customer_order_dispatch',
                        $dispatch->dispatch_date,
                        $dispatch->document_type
                    ),
                    'source_type' => WarehouseDispatch::class,
                    'source_id' => $dispatch->id,
                    'source_item_type' => WarehouseDispatchItem::class,
                    'source_item_id' => $detail->id,
                    'source_key' => "customer-dispatch:{$dispatch->id}:{$detail->id}:{$stock->id}",
                    'document_type' => $dispatch->document_type ?: 'SALIDA DE ALMACÉN',
                    'document_number' => $dispatch->document_number ?: $dispatch->dispatch_number,
                    'related_party_type' => 'customer',
                    'related_party_id' => $order->customer_id,
                    'related_party_name' => $this->customerName($order),
                    'quantity_in' => 0,
                    'quantity_out' => $quantity,
                    'balance_quantity' => $newQuantity,
                    'unit_cost' => $unitCost,
                    'total_cost_in' => 0,
                    'total_cost_out' => $totalCost,
                    'average_unit_cost' => $averageCost,
                    'balance_total_cost' => $newTotalCost,
                    'currency_id' => $order->currency_id,
                    'exchange_rate' => 1,
                    'observations' => $dispatch->observation ?: 'Salida por despacho de OC Cliente',
                    'status' => 'registered',
                    'created_by' => $confirmedBy,
                    'updated_by' => $confirmedBy,
                ]);
                $detail->update([
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'kardex_movement_id' => $movement->id,
                    'status' => WarehouseDispatchItem::STATUS_CONFIRMED,
                ]);
            }

            $dispatch->update([
                'status' => WarehouseDispatch::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => $confirmedBy,
                'updated_by' => $confirmedBy,
            ]);
            $this->statusService->syncStatus($order);

            return $this->dispatchResult($dispatch);
        });
    }

    public function cancelDraft(WarehouseDispatch $dispatch, string $reason): WarehouseDispatch
    {
        return DB::transaction(function () use ($dispatch, $reason) {
            $dispatch = WarehouseDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            if ($dispatch->isCancelled()) {
                return $this->dispatchResult($dispatch);
            }
            if (! $dispatch->isDraft()) {
                throw ValidationException::withMessages([
                    'dispatch' => 'Solo los despachos en borrador pueden cancelarse sin reversa.',
                ]);
            }

            $dispatch->items()->update(['status' => WarehouseDispatchItem::STATUS_CANCELLED]);
            $dispatch->update([
                'status' => WarehouseDispatch::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => $reason,
                'updated_by' => Auth::id(),
            ]);

            return $this->dispatchResult($dispatch);
        });
    }

    public function reverse(WarehouseDispatch $dispatch, string $reason): WarehouseDispatch
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Ingrese un motivo de reversa de 5 a 2000 caracteres.',
            ]);
        }
        $reversedBy = Auth::id();
        if (! $reversedBy) {
            throw ValidationException::withMessages([
                'cancelled_by' => 'No se pudo identificar al usuario que revierte la salida.',
            ]);
        }

        return DB::transaction(function () use ($dispatch, $reason, $reversedBy) {
            $dispatch = WarehouseDispatch::query()->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            if ($dispatch->status !== WarehouseDispatch::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'dispatch' => 'Solo una salida confirmada puede revertirse.',
                ]);
            }

            $order = CustomerPurchaseOrder::query()
                ->whereKey($dispatch->customer_purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $dispatch->company_id !== (int) $order->company_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'La empresa del despacho no coincide con la OC Cliente.',
                ]);
            }
            $details = WarehouseDispatchItem::query()
                ->where('warehouse_dispatch_id', $dispatch->id)
                ->orderBy('warehouse_stock_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($details->isEmpty()) {
                throw ValidationException::withMessages([
                    'dispatch' => 'La salida confirmada no tiene detalles para revertir.',
                ]);
            }
            if ($this->hasActiveInvoiceForDispatchItemIds($details->pluck('id'))) {
                throw ValidationException::withMessages([
                    'dispatch' => 'No puede revertir este despacho porque está vinculado a un comprobante generado. Primero debe cancelar o regularizar el comprobante correspondiente.',
                ]);
            }
            if (CustomerReturn::query()
                ->where('warehouse_dispatch_id', $dispatch->id)
                ->where('status', CustomerReturn::STATUS_CONFIRMED)
                ->whereNull('deleted_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'dispatch' => 'El despacho no puede revertirse porque posee devoluciones de cliente confirmadas. Revise o revierta primero las devoluciones relacionadas.',
                ]);
            }

            $movements = WarehouseKardexMovement::query()
                ->where('company_id', $dispatch->company_id)
                ->where('source_type', WarehouseDispatch::class)
                ->where('source_id', $dispatch->id)
                ->where('source_item_type', WarehouseDispatchItem::class)
                ->whereIn('source_item_id', $details->pluck('id'))
                ->where('operation_type', 'customer_order_dispatch')
                ->where('movement_type', 'exit')
                ->where('status', 'registered')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $movementsByDetail = $movements->keyBy('source_item_id');
            if ($movements->count() !== $details->count()
                || $details->contains(function (WarehouseDispatchItem $detail) use ($movementsByDetail) {
                    $movement = $movementsByDetail->get($detail->id);

                    return ! $movement
                        || (int) $detail->kardex_movement_id !== (int) $movement->id
                        || (int) $detail->warehouse_stock_id !== (int) $movement->warehouse_stock_id;
                })) {
                throw ValidationException::withMessages([
                    'dispatch' => 'La trazabilidad Kardex de la salida está incompleta. No se realizó ninguna reversa.',
                ]);
            }

            $stockIds = $movements->pluck('warehouse_stock_id')->unique()->sort()->values();
            $stocks = WarehouseStock::withTrashed()
                ->where('company_id', $dispatch->company_id)
                ->whereIn('id', $stockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($stocks->count() !== $stockIds->count()) {
                throw ValidationException::withMessages([
                    'dispatch' => 'No se encontraron todos los saldos físicos de la salida. No se realizó ninguna reversa.',
                ]);
            }

            $valuationPools = [];
            foreach ($movements->pluck('article_id')->unique()->sort()->values() as $articleId) {
                $valuationPools[(int) $articleId] = $this->warehouseValuationPoolService->lockPool(
                    (int) $dispatch->company_id,
                    (int) $dispatch->warehouse_id,
                    (int) $articleId,
                    $reversedBy
                );
            }

            foreach ($details as $detail) {
                $movement = $movementsByDetail->get($detail->id);
                $stock = $stocks->get((int) $detail->warehouse_stock_id);
                $this->companyWarehouseService->assertStockOwner($stock, (int) $dispatch->company_id);
                if ((int) $movement->company_id !== (int) $dispatch->company_id) {
                    throw ValidationException::withMessages([
                        'kardex' => 'El movimiento Kardex pertenece a una empresa distinta de la operación.',
                    ]);
                }
                $quantity = round((float) $movement->quantity_out, self::SCALE);
                $cost = round((float) $movement->total_cost_out, 2);
                $valuationPools[(int) $movement->article_id] = $this->warehouseValuationPoolService->addQuantityAtCost(
                    $valuationPools[(int) $movement->article_id],
                    $quantity,
                    $cost,
                    $reversedBy
                );
                $newQuantity = round((float) $stock->current_quantity + $quantity, self::SCALE);
                $currentTotalCost = (float) $stock->current_quantity > 0
                    ? round((float) $stock->total_cost, 2)
                    : 0;
                $newTotalCost = round($currentTotalCost + $cost, 2);
                $averageCost = $this->kardexService->calculateAverageCost($newTotalCost, $newQuantity);

                $stock->update([
                    'current_quantity' => $newQuantity,
                    'total_cost' => $newTotalCost,
                    'average_unit_cost' => $averageCost,
                    'status' => 'ACTIVE',
                    'updated_by' => $reversedBy,
                ]);

                WarehouseKardexMovement::create([
                    'movement_number' => $this->kardexService->generateMovementNumber(),
                    'company_id' => $movement->company_id,
                    'warehouse_stock_id' => $stock->id,
                    'warehouse_id' => $movement->warehouse_id,
                    'article_id' => $movement->article_id,
                    'unit_id' => $movement->unit_id,
                    ...$this->kardexService->accountingSnapshots(
                        (int) $movement->company_id,
                        (int) $movement->warehouse_id,
                        (int) $movement->article_id,
                        $movement->unit_id ? (int) $movement->unit_id : null,
                        $movement
                    ),
                    'presentation_id' => $movement->presentation_id,
                    'brand_id' => $movement->brand_id,
                    'lot_number' => $movement->lot_number,
                    'expiration_date' => $movement->expiration_date,
                    'origin' => $movement->origin,
                    'cost_type' => $movement->cost_type,
                    'movement_date' => now(),
                    'movement_type' => 'exit_reversal',
                    'operation_type' => 'customer_order_dispatch_cancel',
                    ...$this->kardexService->sunatDocumentOperationSnapshots(
                        'exit_reversal',
                        'customer_order_dispatch_cancel',
                        originalMovement: $movement
                    ),
                    'source_type' => WarehouseDispatch::class,
                    'source_id' => $dispatch->id,
                    'source_item_type' => $movement->source_item_type,
                    'source_item_id' => $movement->source_item_id,
                    'source_key' => "customer-dispatch-reversal:{$movement->id}",
                    'document_type' => $movement->document_type,
                    'document_number' => $movement->document_number,
                    'related_party_type' => $movement->related_party_type,
                    'related_party_id' => $movement->related_party_id,
                    'related_party_name' => $movement->related_party_name,
                    'quantity_in' => $quantity,
                    'quantity_out' => 0,
                    'balance_quantity' => $newQuantity,
                    'unit_cost' => $movement->unit_cost,
                    'total_cost_in' => $cost,
                    'total_cost_out' => 0,
                    'average_unit_cost' => $averageCost,
                    'balance_total_cost' => $newTotalCost,
                    'currency_id' => $movement->currency_id,
                    'exchange_rate' => $movement->exchange_rate,
                    'observations' => $reason,
                    'status' => 'registered',
                    'created_by' => $reversedBy,
                    'updated_by' => $reversedBy,
                ]);
            }

            $dispatch->items()->update(['status' => WarehouseDispatchItem::STATUS_REVERSED]);
            $dispatch->update([
                'status' => WarehouseDispatch::STATUS_REVERSED,
                'cancelled_at' => now(),
                'cancelled_by' => $reversedBy,
                'cancellation_reason' => $reason,
                'updated_by' => $reversedBy,
            ]);
            $this->statusService->syncStatus($order);

            return $dispatch->fresh(['warehouse', 'responsibleUser', 'creator', 'cancelledBy', 'items']);
        });
    }

    public function canReverse(WarehouseDispatch $dispatch): bool
    {
        if (! $dispatch->isConfirmed()) {
            return false;
        }

        $itemIds = $dispatch->relationLoaded('items')
            ? $dispatch->items->pluck('id')
            : $dispatch->items()->pluck('id');

        return $itemIds->isNotEmpty()
            && ! $this->hasActiveInvoiceForDispatchItemIds($itemIds)
            && ! CustomerReturn::query()
                ->where('warehouse_dispatch_id', $dispatch->id)
                ->where('status', CustomerReturn::STATUS_CONFIRMED)
                ->whereNull('deleted_at')
                ->exists();
    }

    private function hasActiveInvoiceForDispatchItemIds(Collection $itemIds): bool
    {
        if ($itemIds->isEmpty()) {
            return false;
        }

        return ElectronicInvoiceItemDispatchAllocation::query()
            ->join(
                'electronic_invoice_items as reverse_invoice_items',
                'reverse_invoice_items.id',
                '=',
                'electronic_invoice_item_dispatch_allocations.electronic_invoice_item_id'
            )
            ->join(
                'electronic_invoices as reverse_invoices',
                'reverse_invoices.id',
                '=',
                'reverse_invoice_items.electronic_invoice_id'
            )
            ->whereIn('electronic_invoice_item_dispatch_allocations.warehouse_dispatch_item_id', $itemIds)
            ->whereNull('reverse_invoices.deleted_at')
            ->whereNotIn('reverse_invoices.status', ['draft', 'cancelled', 'voided'])
            ->where('reverse_invoices.is_voided', false)
            ->exists();
    }

    private function validatedDraftRows(CustomerPurchaseOrder $order, array $data): Collection
    {
        if (blank($data['warehouse_id'] ?? null)) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Seleccione un almacén para guardar el borrador.',
            ]);
        }
        if (in_array($order->status, ['cancelled', 'delivered', 'invoiced', 'not_attended'], true)) {
            throw ValidationException::withMessages([
                'customer_purchase_order_id' => 'La OC Cliente no admite nuevos borradores de salida en su estado actual.',
            ]);
        }
        $this->companyWarehouseService->assertEnabled(
            (int) $order->company_id,
            (int) $data['warehouse_id']
        );

        $rows = collect($data['items'] ?? [])->map(function ($item) {
            if (! is_array($item)
                || empty($item['customer_purchase_order_item_id'])
                || empty($item['warehouse_stock_id'])) {
                throw ValidationException::withMessages([
                    'items' => 'Cada asignación debe indicar el artículo y el lote/stock seleccionado.',
                ]);
            }

            return [
                'customer_purchase_order_item_id' => (int) $item['customer_purchase_order_item_id'],
                'warehouse_stock_id' => (int) $item['warehouse_stock_id'],
                'quantity' => $this->validatedQuantity($item['quantity'] ?? null),
            ];
        })->values();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Registre al menos una cantidad para el borrador.']);
        }

        $duplicates = $rows->groupBy(fn (array $row) =>
            $row['customer_purchase_order_item_id'].'|'.$row['warehouse_stock_id']
        )->contains(fn (Collection $group) => $group->count() > 1);
        if ($duplicates) {
            throw ValidationException::withMessages([
                'items' => 'El lote/stock seleccionado está repetido para este artículo.',
            ]);
        }

        $order->loadMissing(['items.article']);
        $orderItems = $order->items->where('status', '!=', 'deleted')->keyBy('id');
        $entered = $this->enteredQuantities($order);
        $dispatched = $this->dispatchedQuantities($order);
        foreach ($rows->groupBy('customer_purchase_order_item_id') as $itemId => $itemRows) {
            $orderItem = $orderItems->get((int) $itemId);
            if (! $orderItem) {
                throw ValidationException::withMessages(['items' => 'Uno de los artículos no pertenece a la OC Cliente.']);
            }
            $this->articleInventoryPolicy->assertCanParticipateInInventory($orderItem->article, 'items');
            $quantity = $itemRows->reduce(
                fn (string $total, array $row) => bcadd($total, $row['quantity'], self::SCALE),
                '0.0000'
            );
            $requested = $this->decimalQuantity($orderItem->quantity);
            $alreadyDispatched = $this->decimalQuantity($dispatched->get((int) $itemId, 0));
            $pending = $this->subtractQuantity($requested, $alreadyDispatched);
            $available = $this->subtractQuantity(
                $this->decimalQuantity($entered->get((int) $itemId, 0)),
                $alreadyDispatched
            );
            if (bccomp($quantity, $pending, self::SCALE) > 0) {
                throw ValidationException::withMessages([
                    'items' => "La cantidad planificada de {$orderItem->billing_name_snapshot} supera el pendiente de la OC Cliente.",
                ]);
            }
            if (bccomp($quantity, $available, self::SCALE) > 0) {
                throw ValidationException::withMessages([
                    'items' => "La cantidad planificada de {$orderItem->billing_name_snapshot} supera lo ingresado para esta OC Cliente.",
                ]);
            }
        }

        $stockIds = $rows->pluck('warehouse_stock_id')->unique()->sort()->values();
        $stocks = WarehouseStock::query()
            ->where('company_id', $order->company_id)
            ->whereIn('id', $stockIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        if ($stocks->count() !== $stockIds->count()) {
            throw ValidationException::withMessages(['items' => 'Uno de los saldos seleccionados ya no existe.']);
        }

        foreach ($rows as $row) {
            $orderItem = $orderItems->get($row['customer_purchase_order_item_id']);
            $stock = $stocks->get($row['warehouse_stock_id']);
            $this->companyWarehouseService->assertStockOwner($stock, (int) $order->company_id);
            if ((int) $stock->warehouse_id !== (int) $data['warehouse_id']) {
                throw ValidationException::withMessages(['items' => 'El saldo seleccionado no pertenece al almacén indicado.']);
            }
            if ((int) $stock->article_id !== (int) $orderItem->article_id) {
                throw ValidationException::withMessages(['items' => 'El saldo seleccionado no pertenece al artículo indicado.']);
            }
            if ($stock->status !== 'ACTIVE' || bccomp($this->decimalQuantity($stock->current_quantity), '0', self::SCALE) <= 0) {
                throw ValidationException::withMessages(['items' => 'No hay stock disponible en el almacén seleccionado.']);
            }
            $this->articleInventoryPolicy->assertCanParticipateInInventory($orderItem->article, 'items');
            if ($orderItem->article?->has_batch && blank($stock->lot_number)) {
                throw ValidationException::withMessages(['items' => "Seleccione un lote para {$orderItem->billing_name_snapshot}."]);
            }
            if ($orderItem->article?->has_expiration && blank($stock->expiration_date)) {
                throw ValidationException::withMessages(['items' => "Seleccione un saldo con vencimiento para {$orderItem->billing_name_snapshot}."]);
            }
        }

        foreach ($rows->groupBy('warehouse_stock_id') as $stockId => $stockRows) {
            $stock = $stocks->get((int) $stockId);
            $quantity = $stockRows->reduce(
                fn (string $total, array $row) => bcadd($total, $row['quantity'], self::SCALE),
                '0.0000'
            );
            if (bccomp($quantity, $this->decimalQuantity($stock->current_quantity), self::SCALE) > 0) {
                throw ValidationException::withMessages([
                    'items' => 'La cantidad planificada supera el stock disponible del saldo seleccionado.',
                ]);
            }
        }

        return $rows->map(function (array $row) use ($orderItems, $stocks) {
            $row['order_item'] = $orderItems->get($row['customer_purchase_order_item_id']);
            $row['stock'] = $stocks->get($row['warehouse_stock_id']);

            return $row;
        });
    }

    private function syncDraftItems(WarehouseDispatch $dispatch, Collection $rows): void
    {
        $existing = $dispatch->items()->get()->keyBy(fn (WarehouseDispatchItem $item) =>
            $item->customer_purchase_order_item_id.'|'.$item->warehouse_stock_id
        );
        $retainedIds = [];

        foreach ($rows as $row) {
            $key = $row['customer_purchase_order_item_id'].'|'.$row['warehouse_stock_id'];
            $stock = $row['stock'];
            $quantity = $row['quantity'];
            $unitCost = round((float) $stock->average_unit_cost, 6);
            $item = $existing->get($key) ?: new WarehouseDispatchItem([
                'warehouse_dispatch_id' => $dispatch->id,
            ]);
            $item->fill([
                'customer_purchase_order_item_id' => $row['customer_purchase_order_item_id'],
                'warehouse_stock_id' => $stock->id,
                'article_id' => $stock->article_id,
                'unit_id' => $stock->unit_id,
                'presentation_id' => $stock->presentation_id,
                'brand_id' => $stock->brand_id,
                'lot_number' => $stock->lot_number,
                'expiration_date' => $stock->expiration_date,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round((float) $quantity * $unitCost, 2),
                'kardex_movement_id' => null,
                'status' => WarehouseDispatchItem::STATUS_DRAFT,
            ]);
            $item->save();
            $retainedIds[] = $item->id;
        }

        $dispatch->items()->whereNotIn('id', $retainedIds)->delete();
    }

    private function dispatchResult(WarehouseDispatch $dispatch): WarehouseDispatch
    {
        return $dispatch->fresh([
            'warehouse',
            'responsibleUser',
            'creator',
            'confirmedBy',
            'cancelledBy',
            'documents.documentType',
            'items.customerPurchaseOrderItem.article',
            'items.stock',
        ]);
    }

    public function enteredQuantities(CustomerPurchaseOrder $order): Collection
    {
        $itemIds = $order->items()->where('status', '!=', 'deleted')->pluck('id');
        if ($itemIds->isEmpty()) {
            return collect();
        }

        $entered = Schema::hasTable('warehouse_entry_item_allocations')
            ? DB::table('warehouse_entry_item_allocations as allocations')
                ->join('warehouse_entries as entries', 'entries.id', '=', 'allocations.warehouse_entry_id')
                ->whereIn('allocations.customer_purchase_order_item_id', $itemIds)
                ->whereNull('allocations.deleted_at')
                ->whereNull('entries.deleted_at')
                ->where('allocations.status', 'active')
                ->where('entries.status', 'registered')
                ->groupBy('allocations.customer_purchase_order_item_id')
                ->selectRaw('allocations.customer_purchase_order_item_id, SUM(allocations.quantity_allocated) total')
                ->pluck('total', 'allocations.customer_purchase_order_item_id')
            : collect();

        $legacy = DB::table('warehouse_entry_items as entry_items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'entry_items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'entry_items.supplier_purchase_order_item_id')
            ->whereIn('supplier_items.customer_purchase_order_item_id', $itemIds)
            ->whereNull('entries.deleted_at')
            ->where('entries.status', 'registered')
            ->where('entry_items.status', '!=', 'deleted')
            ->when(Schema::hasTable('warehouse_entry_item_allocations'), function ($query) {
                $query->whereNotExists(function ($subquery) {
                    $subquery->selectRaw('1')->from('warehouse_entry_item_allocations as allocations')
                        ->whereColumn('allocations.warehouse_entry_item_id', 'entry_items.id')
                        ->where('allocations.status', 'active')->whereNull('allocations.deleted_at');
                });
            })
            ->groupBy('supplier_items.customer_purchase_order_item_id')
            ->selectRaw('supplier_items.customer_purchase_order_item_id, SUM(entry_items.quantity) total')
            ->pluck('total', 'supplier_items.customer_purchase_order_item_id');

        $legacy->each(fn ($quantity, $itemId) => $entered->put(
            (int) $itemId,
            bcadd(
                $this->decimalQuantity($entered->get($itemId, 0)),
                $this->decimalQuantity($quantity),
                self::SCALE
            )
        ));

        return $entered->mapWithKeys(
            fn ($quantity, $itemId) => [(int) $itemId => $this->decimalQuantity($quantity)]
        );
    }

    public function dispatchedQuantities(CustomerPurchaseOrder $order): Collection
    {
        if (! Schema::hasTable('warehouse_dispatch_items')) {
            return collect();
        }

        $dispatched = DB::table('warehouse_dispatch_items as items')
            ->join('warehouse_dispatches as dispatches', 'dispatches.id', '=', 'items.warehouse_dispatch_id')
            ->where('dispatches.customer_purchase_order_id', $order->id)
            ->where('dispatches.status', WarehouseDispatch::STATUS_CONFIRMED)
            ->whereIn('items.status', [
                WarehouseDispatchItem::STATUS_CONFIRMED,
                WarehouseDispatchItem::STATUS_LEGACY_CONFIRMED,
            ])
            ->groupBy('items.customer_purchase_order_item_id')
            ->selectRaw('items.customer_purchase_order_item_id, SUM(items.quantity) total')
            ->pluck('total', 'items.customer_purchase_order_item_id')
            ->mapWithKeys(fn ($quantity, $itemId) => [(int) $itemId => $this->decimalQuantity($quantity)]);

        if (! Schema::hasTable('customer_return_items')) {
            return $dispatched;
        }

        $returned = DB::table('customer_return_items as return_items')
            ->join('customer_returns as returns', 'returns.id', '=', 'return_items.customer_return_id')
            ->join('warehouse_dispatch_items as dispatch_items', 'dispatch_items.id', '=', 'return_items.warehouse_dispatch_item_id')
            ->where('returns.customer_purchase_order_id', $order->id)
            ->where('returns.status', CustomerReturn::STATUS_CONFIRMED)
            ->whereNull('returns.deleted_at')
            ->where('return_items.status', 'confirmed')
            ->groupBy('dispatch_items.customer_purchase_order_item_id')
            ->selectRaw('dispatch_items.customer_purchase_order_item_id, SUM(return_items.quantity) total')
            ->pluck('total', 'dispatch_items.customer_purchase_order_item_id');

        $returned->each(function ($quantity, $itemId) use ($dispatched) {
            $dispatched->put((int) $itemId, $this->subtractQuantity(
                $this->decimalQuantity($dispatched->get((int) $itemId, 0)),
                $this->decimalQuantity($quantity)
            ));
        });

        return $dispatched;
    }

    private function validatedQuantity(mixed $value): string
    {
        $quantity = is_scalar($value) ? trim((string) $value) : '';
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $quantity)
            || bccomp($quantity, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Cada cantidad a despachar debe ser mayor a 0 y tener como máximo 4 decimales.',
            ]);
        }

        return bcadd($quantity, '0', self::SCALE);
    }

    private function decimalQuantity(mixed $value): string
    {
        return bcadd((string) ($value ?? 0), '0', self::SCALE);
    }

    private function subtractQuantity(string $minuend, string $subtrahend): string
    {
        $difference = bcsub($minuend, $subtrahend, self::SCALE);

        return bccomp($difference, '0', self::SCALE) < 0 ? '0.0000' : $difference;
    }

    private function generateDispatchNumber(): string
    {
        $last = WarehouseDispatch::query()->lockForUpdate()->pluck('dispatch_number')->map(
            fn ($number) => preg_match('/^SAL-(\d{6,})$/', (string) $number, $matches) ? (int) $matches[1] : 0
        )->max() ?? 0;

        do {
            $number = 'SAL-'.str_pad((string) ++$last, 6, '0', STR_PAD_LEFT);
        } while (WarehouseDispatch::query()->where('dispatch_number', $number)->exists());

        return $number;
    }

    private function isRetriableDispatchCollision(UniqueConstraintViolationException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        return str_contains($message, 'warehouse_dispatches_dispatch_number_unique')
            || str_contains($message, 'warehouse_dispatches.dispatch_number')
            || str_contains($message, 'warehouse_dispatches_idempotency_key_unique')
            || str_contains($message, 'warehouse_dispatches.idempotency_key');
    }

    private function customerName(CustomerPurchaseOrder $order): string
    {
        return $order->customer?->business_name
            ?? $order->customer?->full_name
            ?? trim(($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? ''))
            ?: 'CLIENTE';
    }
}

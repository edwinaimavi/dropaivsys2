<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrderLabeling;
use App\Models\CustomerOrderLabelingBox;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CustomerOrderLabelingController extends Controller
{
    private const STATUS_DRAFT = 'DRAFT';
    private const STATUS_GENERATED = 'GENERATED';
    private const STATUS_CANCELLED = 'CANCELLED';

    public function __construct()
    {
        $this->middleware('can:admin.labelings.index')->only(['index', 'createData']);
        $this->middleware('can:admin.labelings.list')->only(['list']);
        $this->middleware('can:admin.labelings.customer-order')->only(['loadCustomerOrder']);
        $this->middleware('can:admin.labelings.store')->only(['store']);
        $this->middleware('can:admin.labelings.update')->only(['edit', 'update']);
        $this->middleware('can:admin.labelings.destroy')->only(['destroy']);
        $this->middleware('can:admin.labelings.show')->only(['show']);
        $this->middleware('can:admin.labelings.pdf')->only(['pdf']);
    }

    public function index()
    {
        return view('admin.labelings.index');
    }

    public function list()
    {
        $labelings = CustomerOrderLabeling::query()
            ->with([
                'customerPurchaseOrder:id,code,purchase_order_number',
                'customer:id,business_name,full_name,first_name,last_name',
            ])
            ->orderByDesc('id');

        return DataTables::of($labelings)
            ->addIndexColumn()
            ->addColumn('order', fn (CustomerOrderLabeling $labeling) =>
                trim(($labeling->customerPurchaseOrder?->code ?? '-') . ' | ' . ($labeling->customerPurchaseOrder?->purchase_order_number ?? '')))
            ->addColumn('customer', fn (CustomerOrderLabeling $labeling) => $this->customerName($labeling->customer))
            ->editColumn('boxes_count', fn (CustomerOrderLabeling $labeling) => (int) $labeling->boxes_count)
            ->editColumn('status', fn (CustomerOrderLabeling $labeling) => $this->statusBadge($labeling->status))
            ->editColumn('created_at', fn (CustomerOrderLabeling $labeling) => $labeling->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-')
            ->addColumn('acciones', function (CustomerOrderLabeling $labeling) {
                return view('admin.labelings.partials.actions', compact('labeling'))->render();
            })
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function createData()
    {
        $orders = CustomerPurchaseOrder::query()
            ->with([
                'company:id,business_name,trade_name',
                'customer:id,business_name,full_name,first_name,last_name',
                'currency:id,symbol,code',
                'items:id,customer_purchase_order_id,status',
            ])
            ->whereIn('status', ['entered', 'partial_entered'])
            ->orderByDesc('id')
            ->get()
            ->filter(function (CustomerPurchaseOrder $order) {
                $entered = $this->enteredQuantities($order->id);
                $labeled = $this->labeledQuantities($order->id);

                return collect($entered)->some(
                    fn ($quantity, $itemId) => round((float) $quantity - (float) ($labeled[$itemId] ?? 0), 2) > 0
                );
            })
            ->map(function (CustomerPurchaseOrder $order) {
                $statusLabel = $order->status === 'partial_entered'
                    ? 'INGRESO PARCIAL'
                    : 'ABASTECIDA';

                return [
                    'id' => $order->id,
                    'text' => sprintf(
                        '%s | %s | %s %s | %s',
                        $order->code,
                        $this->customerName($order->customer),
                        $order->currency?->symbol ?? $order->currency?->code ?? '',
                        number_format((float) $order->grand_total, 2),
                        $statusLabel
                    ),
                ];
            })
            ->values();

        return response()->json(['orders' => $orders]);
    }

    public function loadCustomerOrder($id)
    {
        $order = $this->findAvailableOrder($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->orderPayload($order),
        ]);
    }

    public function store(Request $request)
    {
        return $this->saveLabeling($request);
    }

    public function show($id)
    {
        $labeling = $this->labelingQuery()->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->labelingPayload($labeling),
        ]);
    }

    public function edit($id)
    {
        $labeling = $this->labelingQuery()->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->labelingPayload($labeling),
        ]);
    }

    public function update(Request $request, $id)
    {
        $labeling = CustomerOrderLabeling::query()->findOrFail($id);

        return $this->saveLabeling($request, $labeling);
    }

    public function destroy($id)
    {
        $labeling = CustomerOrderLabeling::query()->findOrFail($id);

        $labeling->update([
            'status' => self::STATUS_CANCELLED,
            'updated_by' => Auth::id(),
        ]);
        $labeling->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Rotulación anulada correctamente.',
        ]);
    }

    public function pdf($id)
    {
        $labeling = $this->labelingQuery()->findOrFail($id);
        $pdf = $this->buildLabelingPdf($labeling);

        try {
            $this->storeLabelingPdf($labeling, $pdf);
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar PDF de rotulación: ' . $e->getMessage());
        }

        return $pdf->stream($labeling->code . '.pdf');
    }

    private function saveLabeling(Request $request, ?CustomerOrderLabeling $labeling = null)
    {
        $wasGenerated = $labeling?->status === self::STATUS_GENERATED;

        $validated = $request->validate([
            'customer_purchase_order_id' => [
                'required',
                Rule::exists('customer_purchase_orders', 'id')
                    ->where(fn ($query) => $query->whereIn('status', ['entered', 'partial_entered'])),
            ],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'guide_number' => ['nullable', 'string', 'max:50'],
            'destination' => ['required', 'string', 'max:255'],
            'boxes_count' => ['required', 'integer', 'min:1', 'max:200'],
            'observations' => ['nullable', 'string'],
            'items_to_label' => ['required', 'array', 'min:1'],
            'items_to_label.*.customer_purchase_order_item_id' => ['required', 'exists:customer_purchase_order_items,id'],
            'items_to_label.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'boxes' => ['required', 'array', 'min:1'],
            'boxes.*.box_number' => ['required', 'integer', 'min:1'],
            'boxes.*.observation' => ['nullable', 'string', 'max:1000'],
            'boxes.*.items' => ['required', 'array', 'min:1'],
            'boxes.*.items.*.customer_purchase_order_item_id' => ['required', 'exists:customer_purchase_order_items,id'],
            'boxes.*.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ], [
            'customer_purchase_order_id.required' => 'Seleccione una orden de compra abastecida.',
            'customer_purchase_order_id.exists' => 'La orden seleccionada no está disponible para rotulación.',
            'destination.required' => 'El destino es obligatorio.',
            'destination.max' => 'El destino no debe exceder 255 caracteres.',
            'boxes_count.required' => 'Ingrese la cantidad de cajas.',
            'boxes_count.min' => 'La cantidad de cajas debe ser mayor a cero.',
            'items_to_label.required' => 'Seleccione al menos un artículo con cantidad a rotular.',
            'items_to_label.*.quantity.min' => 'La cantidad a rotular debe ser mayor a cero.',
            'boxes.required' => 'Debe generar la distribución por cajas.',
            'boxes.*.items.required' => 'Cada caja debe tener al menos un artículo.',
            'boxes.*.items.min' => 'Cada caja debe tener al menos un artículo.',
            'boxes.*.items.*.quantity.min' => 'Las cantidades deben ser mayores a cero.',
        ]);

        try {
            return DB::transaction(function () use ($validated, $labeling, $wasGenerated) {
                $order = $this->findAvailableOrder($validated['customer_purchase_order_id'], true);
                $itemMap = $order->items->keyBy('id');
                $entered = $this->enteredQuantities($order->id);
                $entryMeta = $this->enteredItemMeta($order->id);
                $alreadyLabeled = $this->labeledQuantities($order->id, $labeling?->id);
                $requested = [];
                $distributed = [];
                $boxes = [];
                $boxNumbers = [];

                if (count($validated['boxes']) !== (int) $validated['boxes_count']) {
                    throw ValidationException::withMessages([
                        'boxes' => 'La cantidad de cajas generadas no coincide con la cantidad indicada.',
                    ]);
                }

                foreach ($validated['items_to_label'] as $selectedItem) {
                    $itemId = (int) $selectedItem['customer_purchase_order_item_id'];
                    $quantity = round((float) $selectedItem['quantity'], 2);

                    if (!$itemMap->has($itemId)) {
                        throw ValidationException::withMessages([
                            'items_to_label' => 'Uno de los artículos seleccionados no pertenece a la orden seleccionada.',
                        ]);
                    }

                    $available = round((float) ($entered[$itemId] ?? 0) - (float) ($alreadyLabeled[$itemId] ?? 0), 2);

                    if ($quantity > $available) {
                        throw ValidationException::withMessages([
                            'items_to_label' => 'La cantidad a rotular de ' . $this->itemDescription($itemMap[$itemId]) . ' supera la cantidad disponible.',
                        ]);
                    }

                    $requested[$itemId] = round(($requested[$itemId] ?? 0) + $quantity, 2);
                }

                foreach ($validated['boxes'] as $box) {
                    $boxNumber = (int) $box['box_number'];

                    if ($boxNumber > (int) $validated['boxes_count'] || isset($boxNumbers[$boxNumber])) {
                        throw ValidationException::withMessages([
                            'boxes' => 'La numeración de las cajas debe ser única y correlativa del 1 al ' . $validated['boxes_count'] . '.',
                        ]);
                    }

                    $boxNumbers[$boxNumber] = true;
                    $items = collect($box['items'] ?? [])
                        ->filter(fn ($item) => (float) ($item['quantity'] ?? 0) > 0)
                        ->values();

                    if ($items->isEmpty()) {
                        throw ValidationException::withMessages([
                            'boxes' => 'La caja ' . ($box['box_number'] ?? '-') . '/' . $validated['boxes_count'] . ' no tiene artículos.',
                        ]);
                    }

                    $boxes[] = [
                        'box_number' => $boxNumber,
                        'observation' => $this->upperOrNull($box['observation'] ?? null),
                        'items' => $items->all(),
                    ];

                    foreach ($items as $item) {
                        $itemId = (int) $item['customer_purchase_order_item_id'];

                        if (!$itemMap->has($itemId)) {
                            throw ValidationException::withMessages([
                                'items' => 'Uno de los artículos no pertenece a la orden seleccionada.',
                            ]);
                        }

                        if (!isset($requested[$itemId])) {
                            throw ValidationException::withMessages([
                                'items' => 'La caja ' . (int) $box['box_number'] . ' contiene un artículo que no fue seleccionado para rotular.',
                            ]);
                        }

                        $distributed[$itemId] = round(($distributed[$itemId] ?? 0) + (float) $item['quantity'], 2);
                    }
                }

                foreach ($requested as $itemId => $quantity) {
                    $distributedQuantity = round((float) ($distributed[$itemId] ?? 0), 2);
                    $difference = round($distributedQuantity - $quantity, 2);

                    if ($difference > 0) {
                        throw ValidationException::withMessages([
                            'items' => 'La distribución por cajas del artículo ' . $this->itemDescription($itemMap[$itemId])
                                . ' excede la cantidad a rotular por ' . number_format($difference, 2) . ' unidades.',
                        ]);
                    }

                    if ($difference < 0) {
                        throw ValidationException::withMessages([
                            'items' => 'La distribución por cajas del artículo ' . $this->itemDescription($itemMap[$itemId])
                                . ' no cuadra. Faltan ' . number_format(abs($difference), 2) . ' unidades por distribuir.',
                        ]);
                    }
                }

                $totalQuantity = round(array_sum($distributed), 2);
                $data = [
                    'customer_purchase_order_id' => $order->id,
                    'company_id' => $order->company_id,
                    'customer_id' => $order->customer_id,
                    'customer_branch_id' => $order->customer_branch_id,
                    'invoice_number' => $this->upperOrNull($validated['invoice_number'] ?? null),
                    'guide_number' => $this->upperOrNull($validated['guide_number'] ?? null),
                    'destination' => $this->upperOrNull($validated['destination'] ?? null),
                    'boxes_count' => (int) $validated['boxes_count'],
                    'total_quantity' => $totalQuantity,
                    'status' => $wasGenerated ? self::STATUS_GENERATED : ($labeling?->status ?? self::STATUS_DRAFT),
                    'pdf_path' => $wasGenerated ? null : ($labeling?->pdf_path),
                    'observations' => $this->upperOrNull($validated['observations'] ?? null),
                    'updated_by' => Auth::id(),
                ];

                if ($labeling) {
                    $labeling->update($data);
                    $labeling->boxes()->delete();
                } else {
                    $data['code'] = $this->nextCode();
                    $data['created_by'] = Auth::id();
                    $labeling = CustomerOrderLabeling::create($data);
                }

                foreach ($boxes as $boxData) {
                    $box = $labeling->boxes()->create([
                        'box_number' => $boxData['box_number'],
                        'observation' => $boxData['observation'],
                        'observations' => $boxData['observation'],
                        'box_label' => $boxData['box_number'] . '/' . $validated['boxes_count'],
                    ]);

                    foreach ($boxData['items'] as $itemData) {
                        $orderItem = $itemMap[(int) $itemData['customer_purchase_order_item_id']];
                        $meta = $entryMeta[$orderItem->id] ?? [];
                        $box->items()->create([
                            'customer_purchase_order_item_id' => $orderItem->id,
                            'article_id' => $orderItem->article_id,
                            'article_code' => $orderItem->article_code ?? $orderItem->article?->code,
                            'description' => $this->pdfDescription($orderItem, $meta),
                            'unit_name' => $this->unitName($orderItem),
                            'quantity' => round((float) $itemData['quantity'], 2),
                            'lot' => $meta['lot'] ?? null,
                            'expiration_date' => $meta['expiration_date'] ?? $orderItem->expiration_date,
                            'brand_name' => $meta['brand_name'] ?? $orderItem->brand?->description,
                            'origin' => $meta['origin'] ?? $orderItem->origin,
                        ]);
                    }
                }

                if ($wasGenerated) {
                    try {
                        $this->storeLabelingPdf($labeling->load([
                            'company',
                            'customer',
                            'customerBranch',
                            'customerPurchaseOrder',
                            'boxes.items',
                        ]));
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo regenerar PDF de rotulaciÃ³n: ' . $e->getMessage());
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Rotulación guardada correctamente.',
                    'data' => [
                        'id' => $labeling->id,
                        'code' => $labeling->code,
                        'pdf_url' => route('admin.labelings.pdf', $labeling->id),
                    ],
                ], $labeling->wasRecentlyCreated ? 201 : 200);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error saving labeling: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar la rotulación.',
            ], 500);
        }
    }
    private function findAvailableOrder($id, bool $lock = false): CustomerPurchaseOrder
    {
        $query = CustomerPurchaseOrder::query()
            ->with([
                'company:id,business_name,trade_name,logo',
                'customer:id,business_name,full_name,first_name,last_name',
                'customerBranch:id,branch_name,address',
                'items.article:id,code,billing_name,commercial_name,legal_name',
                'items.unit:id,description,abbreviation',
                'items.presentation:id,description',
                'items.brand:id,description',
            ])
            ->whereIn('status', ['entered', 'partial_entered'])
            ->whereKey($id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function orderPayload(CustomerPurchaseOrder $order, ?int $ignoreLabelingId = null): array
    {
        $labeled = $this->labeledQuantities($order->id, $ignoreLabelingId);
        $entered = $this->enteredQuantities($order->id);
        $entryMeta = $this->enteredItemMeta($order->id);

        return [
            'id' => $order->id,
            'code' => $order->code,
            'purchase_order_number' => $order->purchase_order_number,
            'company_id' => $order->company_id,
            'company_name' => $order->company?->trade_name ?? $order->company?->business_name,
            'customer_id' => $order->customer_id,
            'customer_name' => $this->customerName($order->customer),
            'customer_branch_id' => $order->customer_branch_id,
            'customer_branch_name' => $order->customerBranch?->branch_name,
            'destination' => $this->defaultDestination($order),
            'items' => $order->items
                ->where('status', '!=', 'deleted')
                ->map(function (CustomerPurchaseOrderItem $item) use ($labeled, $entered, $entryMeta) {
                    $quantity = round((float) ($entered[$item->id] ?? 0), 2);
                    $labeledQuantity = round((float) ($labeled[$item->id] ?? 0), 2);
                    $meta = $entryMeta[$item->id] ?? [];

                    return [
                        'id' => $item->id,
                        'article_id' => $item->article_id,
                        'article_code' => $item->article_code ?? $item->article?->code,
                        'article_name' => $this->itemDescription($item),
                        'description' => $this->pdfDescription($item, $meta),
                        'unit_name' => $this->unitName($item),
                        'brand_name' => $meta['brand_name'] ?? $item->brand?->description,
                        'presentation_name' => $item->presentation?->description,
                        'lot' => $meta['lot'] ?? null,
                        'expiration_date' => $meta['expiration_date'] ?? $item->expiration_date?->format('Y-m-d'),
                        'origin' => $meta['origin'] ?? $item->origin,
                        'quantity' => $quantity,
                        'labeled_quantity' => $labeledQuantity,
                        'available_quantity' => max(round($quantity - $labeledQuantity, 2), 0),
                    ];
                })
                ->filter(fn (array $item) => $item['quantity'] > 0 || $item['labeled_quantity'] > 0)
                ->values(),
        ];
    }

    private function labelingPayload(CustomerOrderLabeling $labeling): array
    {
        $order = $this->findOrderWithRelations($labeling->customer_purchase_order_id);

        return [
            'id' => $labeling->id,
            'code' => $labeling->code,
            'customer_purchase_order_id' => $labeling->customer_purchase_order_id,
            'invoice_number' => $labeling->invoice_number,
            'guide_number' => $labeling->guide_number,
            'destination' => $labeling->destination,
            'boxes_count' => $labeling->boxes_count,
            'observations' => $labeling->observations,
            'status' => $labeling->status,
            'order' => $this->orderPayload($order, $labeling->id),
            'boxes' => $labeling->boxes->map(fn (CustomerOrderLabelingBox $box) => [
                'box_number' => $box->box_number,
                'box_label' => $box->box_label,
                'observation' => $box->observation ?? $box->observations,
                'items' => $box->items->map(fn ($item) => [
                    'customer_purchase_order_item_id' => $item->customer_purchase_order_item_id,
                    'description' => $item->description,
                    'unit_name' => $item->unit_name,
                    'lot' => $item->lot,
                    'expiration_date' => $item->expiration_date?->format('Y-m-d'),
                    'brand_name' => $item->brand_name,
                    'origin' => $item->origin,
                    'quantity' => (float) $item->quantity,
                ])->values(),
            ])->values(),
        ];
    }

    private function labelingQuery()
    {
        return CustomerOrderLabeling::query()
            ->with([
                'company',
                'customer',
                'customerBranch',
                'customerPurchaseOrder',
                'boxes.items',
            ]);
    }

    private function findOrderWithRelations($id): CustomerPurchaseOrder
    {
        return CustomerPurchaseOrder::query()
            ->with([
                'company:id,business_name,trade_name,logo',
                'customer:id,business_name,full_name,first_name,last_name',
                'customerBranch:id,branch_name,address',
                'items.article:id,code,billing_name,commercial_name,legal_name',
                'items.unit:id,description,abbreviation',
                'items.presentation:id,description',
                'items.brand:id,description',
            ])
            ->whereKey($id)
            ->firstOrFail();
    }

    private function labeledQuantities(int $orderId, ?int $ignoreLabelingId = null): array
    {
        return DB::table('customer_order_labeling_box_items as items')
            ->join('customer_order_labeling_boxes as boxes', 'boxes.id', '=', 'items.customer_order_labeling_box_id')
            ->join('customer_order_labelings as labelings', 'labelings.id', '=', 'boxes.customer_order_labeling_id')
            ->where('labelings.customer_purchase_order_id', $orderId)
            ->where('labelings.status', '!=', self::STATUS_CANCELLED)
            ->whereNull('labelings.deleted_at')
            ->when($ignoreLabelingId, fn ($query) => $query->where('labelings.id', '!=', $ignoreLabelingId))
            ->groupBy('items.customer_purchase_order_item_id')
            ->selectRaw('items.customer_purchase_order_item_id, SUM(items.quantity) as quantity')
            ->pluck('quantity', 'customer_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2))
            ->all();
    }

    private function enteredQuantities(int $orderId): array
    {
        return DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'items.supplier_purchase_order_item_id')
            ->join('customer_purchase_order_items as customer_items', 'customer_items.id', '=', 'supplier_items.customer_purchase_order_item_id')
            ->join('supplier_purchase_orders as supplier_orders', 'supplier_orders.id', '=', 'supplier_items.supplier_purchase_order_id')
            ->where('customer_items.customer_purchase_order_id', $orderId)
            ->whereNull('entries.deleted_at')
            ->whereNull('supplier_orders.deleted_at')
            ->where('entries.status', 'registered')
            ->where('items.status', '!=', 'deleted')
            ->where('supplier_items.status', '!=', 'deleted')
            ->groupBy('supplier_items.customer_purchase_order_item_id')
            ->selectRaw('supplier_items.customer_purchase_order_item_id, SUM(items.quantity) as quantity')
            ->pluck('quantity', 'customer_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2))
            ->all();
    }

    private function enteredItemMeta(int $orderId): array
    {
        return DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'items.supplier_purchase_order_item_id')
            ->join('customer_purchase_order_items as customer_items', 'customer_items.id', '=', 'supplier_items.customer_purchase_order_item_id')
            ->join('supplier_purchase_orders as supplier_orders', 'supplier_orders.id', '=', 'supplier_items.supplier_purchase_order_id')
            ->leftJoin('brands', 'brands.id', '=', 'items.brand_id')
            ->where('customer_items.customer_purchase_order_id', $orderId)
            ->whereNull('entries.deleted_at')
            ->whereNull('supplier_orders.deleted_at')
            ->where('entries.status', 'registered')
            ->where('items.status', '!=', 'deleted')
            ->where('supplier_items.status', '!=', 'deleted')
            ->orderBy('entries.created_at')
            ->orderBy('items.id')
            ->select([
                'supplier_items.customer_purchase_order_item_id',
                'items.lot_number as lot',
                'items.expiration_date',
                'brands.description as brand_name',
                'items.origin',
            ])
            ->get()
            ->groupBy('customer_purchase_order_item_id')
            ->mapWithKeys(function ($rows, $itemId) {
                $row = $rows->first(fn ($entryItem) => filled($entryItem->lot))
                    ?? $rows->first();

                return [
                    (int) $itemId => [
                        'lot' => $row?->lot,
                        'expiration_date' => $row?->expiration_date,
                        'brand_name' => $row?->brand_name,
                        'origin' => $row?->origin,
                    ],
                ];
            })
            ->all();
    }

    private function nextCode(): string
    {
        $lastNumber = CustomerOrderLabeling::withTrashed()
            ->where('code', 'like', 'ROT-%')
            ->pluck('code')
            ->map(fn (?string $code) => preg_match('/^ROT-(\d{6,})$/', (string) $code, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        do {
            $lastNumber++;
            $code = 'ROT-' . str_pad($lastNumber, 6, '0', STR_PAD_LEFT);
        } while (CustomerOrderLabeling::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function statusBadge(string $status): string
    {
        $map = [
            self::STATUS_DRAFT => ['Borrador', 'badge-warning text-dark', 'fas fa-edit'],
            self::STATUS_GENERATED => ['Generado', 'badge-success text-white', 'fas fa-check-circle'],
            self::STATUS_CANCELLED => ['Anulado', 'badge-danger text-white', 'fas fa-ban'],
        ];
        [$label, $class, $icon] = $map[$status] ?? [$status, 'badge-light text-dark border', 'fas fa-info-circle'];

        return sprintf(
            '<span class="badge %s rounded-pill px-3 py-2"><i class="%s mr-1"></i>%s</span>',
            $class,
            $icon,
            e($label)
        );
    }

    private function customerName($customer): string
    {
        return $customer?->business_name
            ?? $customer?->full_name
            ?? trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? ''))
            ?: '-';
    }

    private function itemDescription(CustomerPurchaseOrderItem $item): string
    {
        return $item->billing_name_snapshot
            ?? $item->article?->billing_name
            ?? $item->article?->commercial_name
            ?? $item->article?->legal_name
            ?? 'ARTÍCULO';
    }

    private function pdfDescription(CustomerPurchaseOrderItem $item, array $meta = []): string
    {
        $parts = [$this->itemDescription($item)];

        if ($item->presentation?->description) {
            $parts[] = $item->presentation->description;
        }

        $parts[] = 'LOTE: ' . (!empty($meta['lot']) ? $meta['lot'] : 'SIN LOTE');

        $expirationDate = $meta['expiration_date'] ?? $item->expiration_date;
        $parts[] = 'VCTO: ' . ($expirationDate
            ? (is_string($expirationDate)
                ? date('m/Y', strtotime($expirationDate))
                : $expirationDate->format('m/Y'))
            : 'SIN VCTO');

        $brandName = $meta['brand_name'] ?? $item->brand?->description;
        $parts[] = 'MARCA: ' . ($brandName ?: 'SIN MARCA');

        $origin = $meta['origin'] ?? $item->origin;
        $parts[] = 'PROCEDENCIA: ' . ($origin ?: 'SIN PROCEDENCIA');

        return mb_strtoupper(implode(' - ', array_filter($parts)), 'UTF-8');
    }

    private function unitName(CustomerPurchaseOrderItem $item): ?string
    {
        return $item->unit?->description
            ?? $item->unit?->abbreviation
            ?? null;
    }

    private function buildLabelingPdf(CustomerOrderLabeling $labeling)
    {
        $logoPath = public_path('vendor/adminlte/dist/img/logo_img.png');

        return Pdf::loadView('admin.labelings.pdf', [
            'labeling' => $labeling,
            'logoDataUri' => $this->imageDataUri($logoPath),
            'qrCodes' => $this->labelingQrCodes($labeling),
            'labelsPerPage' => $this->labelsPerPageFor($labeling),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isFontSubsettingEnabled' => true,
                'isRemoteEnabled' => false,
            ]);
    }

    private function storeLabelingPdf(CustomerOrderLabeling $labeling, $pdf = null): string
    {
        $pdf ??= $this->buildLabelingPdf($labeling);
        $fileName = 'labelings/' . $labeling->code . '.pdf';

        Storage::disk('public')->put($fileName, $pdf->output());
        $labeling->update([
            'pdf_path' => $fileName,
            'status' => self::STATUS_GENERATED,
            'updated_by' => Auth::id(),
        ]);

        return $fileName;
    }

    private function labelsPerPageFor(CustomerOrderLabeling $labeling): int
    {
        return 4;
    }

    private function defaultDestination(CustomerPurchaseOrder $order): ?string
    {
        return $this->upperOrNull(
            $order->customerBranch?->branch_name
                ?? $order->customerBranch?->address
                ?? $order->customer?->address
                ?? null
        );
    }

    private function labelingQrCodes(CustomerOrderLabeling $labeling): array
    {
        $customerName = $this->customerName($labeling->customer);
        $orderNumber = $labeling->customerPurchaseOrder?->purchase_order_number
            ?? $labeling->customerPurchaseOrder?->code
            ?? '-';

        return $labeling->boxes->mapWithKeys(function (CustomerOrderLabelingBox $box) use ($labeling, $customerName, $orderNumber) {
            $payload = implode("\n", [
                'ROT: ' . $labeling->code,
                'OC: ' . $orderNumber,
                'CLIENTE: ' . mb_substr($customerName, 0, 55, 'UTF-8'),
                'DESTINO: ' . mb_substr($labeling->destination ?: '-', 0, 45, 'UTF-8'),
                'FACTURA: ' . ($labeling->invoice_number ?: '-'),
                'GUIA: ' . ($labeling->guide_number ?: '-'),
                'CAJA: ' . $box->box_label,
            ]);

            return [$box->id => $this->qrSvgDataUri($payload)];
        })->all();
    }

    private function qrSvgDataUri(string $payload): string
    {
        $matrix = $this->qrMatrix(mb_substr($payload, 0, 190, 'UTF-8'));
        $module = 3;
        $quiet = 4;
        $size = count($matrix);
        $canvas = ($size + ($quiet * 2)) * $module;
        $rects = '';

        foreach ($matrix as $row => $columns) {
            foreach ($columns as $column => $dark) {
                if ($dark) {
                    $rects .= sprintf(
                        '<rect x="%d" y="%d" width="%d" height="%d"/>',
                        ($column + $quiet) * $module,
                        ($row + $quiet) * $module,
                        $module,
                        $module
                    );
                }
            }
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#111827">%s</g></svg>',
            $canvas,
            $canvas,
            $canvas,
            $canvas,
            $rects
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function qrMatrix(string $payload): array
    {
        $version = 6;
        $size = 21 + (($version - 1) * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        $this->qrFinder($matrix, $reserved, 0, 0);
        $this->qrFinder($matrix, $reserved, $size - 7, 0);
        $this->qrFinder($matrix, $reserved, 0, $size - 7);
        $this->qrAlignment($matrix, $reserved, 34, 34);

        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = $i % 2 === 0;
            $matrix[$i][6] = $i % 2 === 0;
            $reserved[6][$i] = true;
            $reserved[$i][6] = true;
        }

        $matrix[$size - 8][8] = true;
        $reserved[$size - 8][8] = true;
        $this->qrReserveFormat($reserved, $size);

        $data = $this->qrDataCodewords($payload);
        $bits = [];
        foreach ($data as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($byte >> $i) & 1) === 1;
            }
        }

        $bitIndex = 0;
        $up = true;
        for ($column = $size - 1; $column > 0; $column -= 2) {
            if ($column === 6) {
                $column--;
            }

            for ($i = 0; $i < $size; $i++) {
                $row = $up ? $size - 1 - $i : $i;

                for ($offset = 0; $offset < 2; $offset++) {
                    $currentColumn = $column - $offset;
                    if ($reserved[$row][$currentColumn]) {
                        continue;
                    }

                    $dark = $bits[$bitIndex] ?? false;
                    if (($row + $currentColumn) % 2 === 0) {
                        $dark = ! $dark;
                    }

                    $matrix[$row][$currentColumn] = $dark;
                    $bitIndex++;
                }
            }

            $up = ! $up;
        }

        $this->qrFormat($matrix, $reserved, $size);

        return $matrix;
    }

    private function qrDataCodewords(string $payload): array
    {
        $bytes = array_values(unpack('C*', mb_convert_encoding($payload, 'UTF-8')));
        $bytes = array_slice($bytes, 0, 132);
        $bits = [0, 1, 0, 0];

        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ((count($bytes) >> $i) & 1) === 1;
        }

        foreach ($bytes as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($byte >> $i) & 1) === 1;
            }
        }

        $capacityBits = 136 * 8;
        for ($i = 0; $i < min(4, $capacityBits - count($bits)); $i++) {
            $bits[] = false;
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = false;
        }

        $data = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $byte = 0;
            foreach ($chunk as $bit) {
                $byte = ($byte << 1) | ($bit ? 1 : 0);
            }
            $data[] = $byte;
        }

        $pads = [0xec, 0x11];
        for ($i = 0; count($data) < 136; $i++) {
            $data[] = $pads[$i % 2];
        }

        $blocks = [array_slice($data, 0, 68), array_slice($data, 68, 68)];
        $eccBlocks = array_map(fn (array $block) => $this->qrReedSolomon($block, 18), $blocks);
        $codewords = [];

        for ($i = 0; $i < 68; $i++) {
            foreach ($blocks as $block) {
                $codewords[] = $block[$i];
            }
        }

        for ($i = 0; $i < 18; $i++) {
            foreach ($eccBlocks as $block) {
                $codewords[] = $block[$i];
            }
        }

        return $codewords;
    }

    private function qrFinder(array &$matrix, array &$reserved, int $x, int $y): void
    {
        $size = count($matrix);
        for ($row = $y - 1; $row <= $y + 7; $row++) {
            for ($column = $x - 1; $column <= $x + 7; $column++) {
                if ($row < 0 || $column < 0 || $row >= $size || $column >= $size) {
                    continue;
                }

                $reserved[$row][$column] = true;
                $inFinder = $row >= $y && $row < $y + 7 && $column >= $x && $column < $x + 7;
                $matrix[$row][$column] = $inFinder
                    && ($row === $y || $row === $y + 6 || $column === $x || $column === $x + 6
                        || ($row >= $y + 2 && $row <= $y + 4 && $column >= $x + 2 && $column <= $x + 4));
            }
        }
    }

    private function qrAlignment(array &$matrix, array &$reserved, int $centerX, int $centerY): void
    {
        for ($row = $centerY - 2; $row <= $centerY + 2; $row++) {
            for ($column = $centerX - 2; $column <= $centerX + 2; $column++) {
                $reserved[$row][$column] = true;
                $matrix[$row][$column] = $row === $centerY - 2 || $row === $centerY + 2
                    || $column === $centerX - 2 || $column === $centerX + 2
                    || ($row === $centerY && $column === $centerX);
            }
        }
    }

    private function qrReserveFormat(array &$reserved, int $size): void
    {
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }
    }

    private function qrFormat(array &$matrix, array &$reserved, int $size): void
    {
        $formatBits = 0x77c4;
        $positionsA = [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]];
        $positionsB = [[$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8], [$size - 5, 8], [$size - 6, 8], [$size - 7, 8], [$size - 8, 8], [8, $size - 7], [8, $size - 6], [8, $size - 5], [8, $size - 4], [8, $size - 3], [8, $size - 2], [8, $size - 1]];

        foreach ($positionsA as $index => [$row, $column]) {
            $matrix[$row][$column] = (($formatBits >> $index) & 1) === 1;
            $reserved[$row][$column] = true;
        }

        foreach ($positionsB as $index => [$row, $column]) {
            $matrix[$row][$column] = (($formatBits >> $index) & 1) === 1;
            $reserved[$row][$column] = true;
        }
    }

    private function qrReedSolomon(array $data, int $degree): array
    {
        $generator = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $index => $coefficient) {
                $next[$index] ^= $this->qrGfMul($coefficient, 1);
                $next[$index + 1] ^= $this->qrGfMul($coefficient, $this->qrGfPow(2, $i));
            }
            $generator = $next;
        }

        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;

            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= $this->qrGfMul($generator[$i + 1], $factor);
            }
        }

        return $result;
    }

    private function qrGfPow(int $base, int $exponent): int
    {
        $result = 1;
        for ($i = 0; $i < $exponent; $i++) {
            $result = $this->qrGfMul($result, $base);
        }

        return $result;
    }

    private function qrGfMul(int $a, int $b): int
    {
        $result = 0;
        while ($b > 0) {
            if ($b & 1) {
                $result ^= $a;
            }

            $a <<= 1;
            if ($a & 0x100) {
                $a ^= 0x11d;
            }
            $b >>= 1;
        }

        return $result & 0xff;
    }

    private function imageDataUri(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_strtoupper($value, 'UTF-8') : null;
    }
}

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
            ->addColumn('order', fn (CustomerOrderLabeling $labeling) => $labeling->customerPurchaseOrder?->code ?? '-')
            ->addColumn('customer', fn (CustomerOrderLabeling $labeling) => $this->customerName($labeling->customer))
            ->editColumn('boxes_count', fn (CustomerOrderLabeling $labeling) => (int) $labeling->boxes_count)
            ->editColumn('status', fn (CustomerOrderLabeling $labeling) => $this->statusBadge($labeling->status))
            ->editColumn('created_at', fn (CustomerOrderLabeling $labeling) => $labeling->created_at?->format('d/m/Y H:i') ?? '-')
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

        if ($labeling->status !== self::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden editar rotulaciones en borrador.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->labelingPayload($labeling),
        ]);
    }

    public function update(Request $request, $id)
    {
        $labeling = CustomerOrderLabeling::query()->findOrFail($id);

        if ($labeling->status !== self::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden actualizar rotulaciones en borrador.',
            ]);
        }

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
        $pdf = Pdf::loadView('admin.labelings.pdf', [
            'labeling' => $labeling,
            'logoPath' => public_path('vendor/adminlte/dist/img/logo_img.png'),
        ])->setPaper('a4', 'portrait');

        $fileName = 'labelings/' . $labeling->code . '.pdf';

        try {
            Storage::disk('public')->put($fileName, $pdf->output());
            $labeling->update([
                'pdf_path' => $fileName,
                'status' => self::STATUS_GENERATED,
                'updated_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar PDF de rotulación: ' . $e->getMessage());
        }

        return $pdf->stream($labeling->code . '.pdf');
    }

    private function saveLabeling(Request $request, ?CustomerOrderLabeling $labeling = null)
    {
        $validated = $request->validate([
            'customer_purchase_order_id' => [
                'required',
                Rule::exists('customer_purchase_orders', 'id')
                    ->where(fn ($query) => $query->whereIn('status', ['entered', 'partial_entered'])),
            ],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'guide_number' => ['nullable', 'string', 'max:50'],
            'boxes_count' => ['required', 'integer', 'min:1', 'max:200'],
            'observations' => ['nullable', 'string'],
            'boxes' => ['required', 'array', 'min:1'],
            'boxes.*.box_number' => ['required', 'integer', 'min:1'],
            'boxes.*.items' => ['nullable', 'array'],
            'boxes.*.items.*.customer_purchase_order_item_id' => ['required', 'exists:customer_purchase_order_items,id'],
            'boxes.*.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ], [
            'customer_purchase_order_id.required' => 'Seleccione una orden de compra abastecida.',
            'customer_purchase_order_id.exists' => 'La orden seleccionada no está disponible para rotulación.',
            'boxes_count.required' => 'Ingrese la cantidad de cajas.',
            'boxes_count.min' => 'Debe registrar al menos una caja.',
            'boxes.required' => 'Debe generar la distribución por cajas.',
            'boxes.*.items.*.quantity.min' => 'Las cantidades deben ser mayores a cero.',
        ]);

        try {
            return DB::transaction(function () use ($validated, $labeling) {
                $order = $this->findAvailableOrder($validated['customer_purchase_order_id'], true);
                $itemMap = $order->items->keyBy('id');
                $entered = $this->enteredQuantities($order->id);
                $entryMeta = $this->enteredItemMeta($order->id);
                $distributed = [];
                $boxes = [];

                foreach ($validated['boxes'] as $box) {
                    $items = collect($box['items'] ?? [])
                        ->filter(fn ($item) => (float) ($item['quantity'] ?? 0) > 0)
                        ->values();

                    if ($items->isEmpty()) {
                        continue;
                    }

                    $boxes[] = [
                        'box_number' => (int) $box['box_number'],
                        'items' => $items->all(),
                    ];

                    foreach ($items as $item) {
                        $itemId = (int) $item['customer_purchase_order_item_id'];

                        if (!$itemMap->has($itemId)) {
                            throw ValidationException::withMessages([
                                'items' => 'Uno de los artículos no pertenece a la orden seleccionada.',
                            ]);
                        }

                        $distributed[$itemId] = round(($distributed[$itemId] ?? 0) + (float) $item['quantity'], 2);
                    }
                }

                if (empty($boxes)) {
                    throw ValidationException::withMessages([
                        'boxes' => 'Debe distribuir al menos un artículo en una caja.',
                    ]);
                }

                $alreadyLabeled = $this->labeledQuantities($order->id, $labeling?->id);
                foreach ($distributed as $itemId => $quantity) {
                    $available = round((float) ($entered[$itemId] ?? 0) - (float) ($alreadyLabeled[$itemId] ?? 0), 2);

                    if ($quantity > $available) {
                        throw ValidationException::withMessages([
                            'items' => 'La cantidad distribuida supera la cantidad disponible de ' . $this->itemDescription($itemMap[$itemId]) . '.',
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
                    'boxes_count' => (int) $validated['boxes_count'],
                    'total_quantity' => $totalQuantity,
                    'status' => $labeling?->status ?? self::STATUS_DRAFT,
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
            'boxes_count' => $labeling->boxes_count,
            'observations' => $labeling->observations,
            'status' => $labeling->status,
            'order' => $this->orderPayload($order, $labeling->id),
            'boxes' => $labeling->boxes->map(fn (CustomerOrderLabelingBox $box) => [
                'box_number' => $box->box_number,
                'box_label' => $box->box_label,
                'items' => $box->items->map(fn ($item) => [
                    'customer_purchase_order_item_id' => $item->customer_purchase_order_item_id,
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
            ->groupBy('supplier_items.customer_purchase_order_item_id')
            ->selectRaw('
                supplier_items.customer_purchase_order_item_id,
                MIN(items.lot_number) as lot,
                MIN(items.expiration_date) as expiration_date,
                MIN(brands.description) as brand_name,
                MIN(items.origin) as origin
            ')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->customer_purchase_order_item_id => [
                    'lot' => $row->lot,
                    'expiration_date' => $row->expiration_date,
                    'brand_name' => $row->brand_name,
                    'origin' => $row->origin,
                ],
            ])
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

        if (!empty($meta['lot'])) {
            $parts[] = 'LOTE: ' . $meta['lot'];
        }

        $expirationDate = $meta['expiration_date'] ?? $item->expiration_date;
        if ($expirationDate) {
            $parts[] = 'VCTO: ' . (is_string($expirationDate)
                ? date('m/Y', strtotime($expirationDate))
                : $expirationDate->format('m/Y'));
        }

        $brandName = $meta['brand_name'] ?? $item->brand?->description;
        if ($brandName) {
            $parts[] = 'MARCA: ' . $brandName;
        }

        $origin = $meta['origin'] ?? $item->origin;
        if ($origin) {
            $parts[] = 'PROCEDENCIA: ' . $origin;
        }

        return mb_strtoupper(implode(' - ', array_filter($parts)), 'UTF-8');
    }

    private function unitName(CustomerPurchaseOrderItem $item): ?string
    {
        return $item->unit?->description
            ?? $item->unit?->abbreviation
            ?? null;
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_strtoupper($value, 'UTF-8') : null;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseKardexRecalculation;
use App\Models\WarehouseStock;
use App\Services\WarehouseKardexService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class KardexController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.kardex.index')->only(['index', 'list', 'articleHistory']);
        $this->middleware('can:admin.kardex.show')->only(['show']);
        $this->middleware('can:admin.kardex.stock')->only(['stock', 'stockAtDate']);
        $this->middleware('can:admin.kardex.export')->only(['export']);
        $this->middleware('can:admin.kardex.recalculate')->only(['recalculate']);
    }

    public function index()
    {
        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);
        $articles = Article::query()
            ->where('status', 'ACTIVE')
            ->orderBy('billing_name')
            ->get(['id', 'code', 'billing_name']);
        $stats = [
            'stock_articles' => WarehouseStock::query()
                ->where('status', 'ACTIVE')
                ->where('current_quantity', '>', 0)
                ->distinct('article_id')
                ->count('article_id'),
            'month_entries' => WarehouseKardexMovement::query()
                ->where('status', 'registered')
                ->where('movement_type', 'entry')
                ->whereMonth('movement_date', now()->month)
                ->whereYear('movement_date', now()->year)
                ->sum('quantity_in'),
            'month_exits' => WarehouseKardexMovement::query()
                ->where('status', 'registered')
                ->whereIn('movement_type', ['exit', 'adjustment_out', 'transfer_out'])
                ->whereMonth('movement_date', now()->month)
                ->whereYear('movement_date', now()->year)
                ->sum('quantity_out'),
            'inventory_value' => WarehouseStock::query()
                ->where('status', 'ACTIVE')
                ->sum('total_cost'),
        ];
        $lastRecalculation = WarehouseKardexRecalculation::query()
            ->with('creator:id,name,lastname')
            ->latest('started_at')
            ->first();

        return view('admin.kardex.index', compact('warehouses', 'articles', 'stats', 'lastRecalculation'));
    }

    public function list(Request $request)
    {
        $movements = $this->movementQuery($request)
            ->with([
                'warehouse:id,name',
                'article:id,code,billing_name',
                'currency:id,code,symbol',
            ])
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        return DataTables::of($movements)
            ->addIndexColumn()
            ->editColumn('movement_date', fn (WarehouseKardexMovement $movement) => $movement->movement_date?->format('d/m/Y H:i') ?? '-')
            ->addColumn('warehouse', fn (WarehouseKardexMovement $movement) => $movement->warehouse?->name ?? '-')
            ->addColumn('article', fn (WarehouseKardexMovement $movement) => trim(($movement->article?->code ? $movement->article->code.' | ' : '').($movement->article?->billing_name ?? '-')))
            ->editColumn('expiration_date', fn (WarehouseKardexMovement $movement) => $movement->expiration_date?->format('d/m/Y') ?? '-')
            ->editColumn('movement_type', fn (WarehouseKardexMovement $movement) => $this->badge($this->movementTypePresentation($movement->movement_type)))
            ->addColumn('document', fn (WarehouseKardexMovement $movement) => collect([$movement->document_type, $movement->document_series, $movement->document_number])
                ->filter()
                ->implode(' ') ?: '-')
            ->editColumn('quantity_in', fn (WarehouseKardexMovement $movement) => number_format((float) $movement->quantity_in, 2))
            ->editColumn('quantity_out', fn (WarehouseKardexMovement $movement) => number_format((float) $movement->quantity_out, 2))
            ->editColumn('balance_quantity', fn (WarehouseKardexMovement $movement) => number_format((float) $movement->balance_quantity, 2))
            ->addColumn('entry_unit_cost', fn (WarehouseKardexMovement $movement) => (float) $movement->quantity_in > 0 ? $this->money($movement->unit_cost, $movement) : '-')
            ->addColumn('entry_total_cost', fn (WarehouseKardexMovement $movement) => (float) $movement->total_cost_in > 0 ? $this->money($movement->total_cost_in, $movement) : '-')
            ->addColumn('exit_unit_cost', fn (WarehouseKardexMovement $movement) => (float) $movement->quantity_out > 0 ? $this->money($movement->unit_cost, $movement) : '-')
            ->addColumn('exit_total_cost', fn (WarehouseKardexMovement $movement) => (float) $movement->total_cost_out > 0 ? $this->money($movement->total_cost_out, $movement) : '-')
            ->addColumn('average_unit_cost_display', fn (WarehouseKardexMovement $movement) => $this->money($movement->average_unit_cost, $movement))
            ->editColumn('balance_total_cost', fn (WarehouseKardexMovement $movement) => $this->money($movement->balance_total_cost, $movement))
            ->editColumn('status', fn (WarehouseKardexMovement $movement) => $this->badge($this->statusPresentation($movement->status)))
            ->addColumn('acciones', fn (WarehouseKardexMovement $movement) => view('admin.kardex.partials.acciones', compact('movement'))->render())
            ->rawColumns(['movement_type', 'status', 'acciones'])
            ->make(true);
    }

    public function show(WarehouseKardexMovement $movement)
    {
        $movement->load([
            'warehouse',
            'article',
            'unit',
            'presentation',
            'brand',
            'currency',
            'stock',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $movement,
            'movement_type_label' => $this->movementTypePresentation($movement->movement_type)['label'],
            'status_label' => $this->statusPresentation($movement->status)['label'],
            'source_label' => match ($movement->operation_type) {
                'electronic_invoice', 'electronic_invoice_cancel' => 'Facturación',
                'warehouse_entry', 'warehouse_entry_cancel',
                'warehouse_entry_linked_cost', 'warehouse_entry_linked_cost_cancel' => 'Ingreso de almacén',
                default => $movement->source_type ? class_basename($movement->source_type) : '-',
            },
            'source_item_label' => $movement->source_item_type ? class_basename($movement->source_item_type) : '-',
            'source_url' => $movement->source_type === WarehouseEntry::class && $movement->source_id
                ? route('admin.warehouse-entries.index', [
                    'from_warehouse_entry' => $movement->source_id,
                    'auto_open' => 1,
                ])
                : null,
        ]);
    }

    public function stockAtDate(Request $request, WarehouseKardexService $service)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'article_id' => ['nullable', 'integer', 'exists:articles,id'],
        ]);

        $items = $service->stockAtDateQuery(
            $validated['date'],
            isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
            isset($validated['article_id']) ? (int) $validated['article_id'] : null
        )->with(['warehouse:id,name', 'article:id,code,billing_name'])->get();

        return response()->json([
            'status' => 'success',
            'date' => $validated['date'],
            'total_quantity' => round((float) $items->sum('balance_quantity'), 4),
            'total_value' => round((float) $items->sum('balance_total_cost'), 2),
            'items' => $items->map(fn ($movement) => [
                'warehouse' => $movement->warehouse?->name,
                'article' => trim(($movement->article?->code ? $movement->article->code.' | ' : '').($movement->article?->billing_name ?? '-')),
                'lot_number' => $movement->lot_number,
                'quantity' => (float) $movement->balance_quantity,
                'average_cost' => (float) $movement->average_unit_cost,
                'total_value' => (float) $movement->balance_total_cost,
            ])->values(),
        ]);
    }

    public function recalculate(Request $request, WarehouseKardexService $service)
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $log = $service->recalculate($validated);

        return response()->json([
            'status' => 'success',
            'message' => "Recálculo completado: {$log->movements_processed} movimientos y {$log->stocks_processed} saldos procesados.",
            'data' => $log,
        ]);
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['excel', 'pdf', 'print'], true), 404);
        $movements = $this->movementQuery($request)
            ->with(['warehouse', 'article', 'currency'])
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();
        $viewData = ['movements' => $movements, 'filters' => $request->all(), 'format' => $format];

        if ($format === 'pdf') {
            return Pdf::loadView('admin.kardex.report', $viewData)
                ->setPaper('a4', 'landscape')
                ->download('kardex-valorizado-'.now()->format('Ymd-His').'.pdf');
        }

        if ($format === 'excel') {
            return response(view('admin.kardex.report', $viewData)->render(), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="kardex-valorizado-'.now()->format('Ymd-His').'.xls"',
            ]);
        }

        return view('admin.kardex.report', $viewData);
    }

    public function stock(Request $request)
    {
        $stocks = WarehouseStock::query()
            ->with([
                'warehouse:id,name',
                'article:id,code,billing_name',
                'unit:id,description',
                'presentation:id,description',
                'brand:id,description',
            ])
            ->when($request->warehouse_id, fn ($query, $value) => $query->where('warehouse_id', $value))
            ->when($request->article_id, fn ($query, $value) => $query->where('article_id', $value))
            ->where('status', 'ACTIVE')
            ->orderBy('warehouse_id')
            ->orderBy('article_id');

        return DataTables::of($stocks)
            ->addIndexColumn()
            ->addColumn('warehouse', fn (WarehouseStock $stock) => $stock->warehouse?->name ?? '-')
            ->addColumn('article', fn (WarehouseStock $stock) => trim(($stock->article?->code ? $stock->article->code.' | ' : '').($stock->article?->billing_name ?? '-')))
            ->addColumn('unit', fn (WarehouseStock $stock) => $stock->unit?->description ?? '-')
            ->editColumn('expiration_date', fn (WarehouseStock $stock) => $stock->expiration_date?->format('d/m/Y') ?? '-')
            ->editColumn('current_quantity', fn (WarehouseStock $stock) => number_format((float) $stock->current_quantity, 2))
            ->editColumn('average_unit_cost', fn (WarehouseStock $stock) => number_format((float) $stock->average_unit_cost, 2))
            ->editColumn('total_cost', fn (WarehouseStock $stock) => number_format((float) $stock->total_cost, 2))
            ->make(true);
    }

    public function articleHistory(Article $article)
    {
        return WarehouseKardexMovement::query()
            ->with('warehouse:id,name')
            ->where('article_id', $article->id)
            ->orderByDesc('movement_date')
            ->limit(100)
            ->get();
    }

    private function money(mixed $value, WarehouseKardexMovement $movement): string
    {
        $symbol = $movement->currency?->symbol ?? $movement->currency?->code ?? '';

        return trim($symbol.' '.number_format((float) $value, 2));
    }

    private function badge(array $presentation): string
    {
        return sprintf(
            '<span class="badge %s rounded-pill px-3 py-2 font-weight-bold">%s</span>',
            $presentation['class'],
            e($presentation['label'])
        );
    }

    private function movementTypePresentation(?string $type): array
    {
        return [
            'entry' => ['label' => 'Entrada', 'class' => 'badge-success text-white'],
            'exit' => ['label' => 'Salida', 'class' => 'badge-danger text-white'],
            'adjustment_in' => ['label' => 'Ajuste Entrada', 'class' => 'badge-primary text-white'],
            'adjustment_out' => ['label' => 'Ajuste Salida', 'class' => 'badge-warning text-dark'],
            'transfer_in' => ['label' => 'Transferencia Entrada', 'class' => 'badge-info text-white'],
            'transfer_out' => ['label' => 'Transferencia Salida', 'class' => 'badge-purple text-white'],
            'reversal' => ['label' => 'Reversa', 'class' => 'badge-secondary text-white'],
            'exit_reversal' => ['label' => 'Reversa de salida', 'class' => 'badge-info text-white'],
            'linked_cost' => ['label' => 'Costo vinculado', 'class' => 'badge-info text-white'],
            'cost_reversal' => ['label' => 'Reversa de costo', 'class' => 'badge-secondary text-white'],
        ][$type] ?? ['label' => ucfirst((string) $type), 'class' => 'badge-light text-dark border'];
    }

    private function movementQuery(Request $request): Builder
    {
        return WarehouseKardexMovement::query()
            ->when($request->warehouse_id, fn ($query, $value) => $query->where('warehouse_id', $value))
            ->when($request->article_id, fn ($query, $value) => $query->where('article_id', $value))
            ->when($request->movement_type, fn ($query, $value) => $query->where('movement_type', $value))
            ->when($request->lot_number, fn ($query, $value) => $query->where('lot_number', 'like', "%{$value}%"))
            ->when($request->document, function ($query, $value) {
                $query->where(function ($subQuery) use ($value) {
                    $subQuery->where('document_type', 'like', "%{$value}%")
                        ->orWhere('document_series', 'like', "%{$value}%")
                        ->orWhere('document_number', 'like', "%{$value}%");
                });
            })
            ->when($request->related_party, fn ($query, $value) => $query->where('related_party_name', 'like', "%{$value}%"))
            ->when($request->date_from, fn ($query, $value) => $query->whereDate('movement_date', '>=', $value))
            ->when($request->date_to, fn ($query, $value) => $query->whereDate('movement_date', '<=', $value));
    }

    private function statusPresentation(?string $status): array
    {
        return [
            'registered' => ['label' => 'Registrado', 'class' => 'badge-success text-white'],
            'cancelled' => ['label' => 'Anulado', 'class' => 'badge-danger text-white'],
            'reversed' => ['label' => 'Revertido', 'class' => 'badge-secondary text-white'],
        ][$status] ?? ['label' => ucfirst((string) $status), 'class' => 'badge-light text-dark border'];
    }
}

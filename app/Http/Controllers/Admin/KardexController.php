<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Warehouse;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class KardexController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.kardex.index')->only(['index', 'list', 'articleHistory']);
        $this->middleware('can:admin.kardex.show')->only(['show']);
        $this->middleware('can:admin.kardex.stock')->only(['stock']);
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

        return view('admin.kardex.index', compact('warehouses', 'articles', 'stats'));
    }

    public function list(Request $request)
    {
        $movements = WarehouseKardexMovement::query()
            ->with([
                'warehouse:id,name',
                'article:id,code,billing_name',
                'currency:id,code,symbol',
            ])
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
            ->when($request->date_to, fn ($query, $value) => $query->whereDate('movement_date', '<=', $value))
            ->orderByDesc('movement_date')
            ->orderByDesc('id');

        return DataTables::of($movements)
            ->addIndexColumn()
            ->editColumn('movement_date', fn (WarehouseKardexMovement $movement) =>
                $movement->movement_date?->format('d/m/Y H:i') ?? '-')
            ->addColumn('warehouse', fn (WarehouseKardexMovement $movement) =>
                $movement->warehouse?->name ?? '-')
            ->addColumn('article', fn (WarehouseKardexMovement $movement) =>
                trim(($movement->article?->code ? $movement->article->code . ' | ' : '') . ($movement->article?->billing_name ?? '-')))
            ->editColumn('expiration_date', fn (WarehouseKardexMovement $movement) =>
                $movement->expiration_date?->format('d/m/Y') ?? '-')
            ->editColumn('movement_type', fn (WarehouseKardexMovement $movement) =>
                $this->badge($this->movementTypePresentation($movement->movement_type)))
            ->addColumn('document', fn (WarehouseKardexMovement $movement) =>
                collect([$movement->document_type, $movement->document_series, $movement->document_number])
                    ->filter()
                    ->implode(' ') ?: '-')
            ->editColumn('quantity_in', fn (WarehouseKardexMovement $movement) => number_format((float) $movement->quantity_in, 2))
            ->editColumn('quantity_out', fn (WarehouseKardexMovement $movement) => number_format((float) $movement->quantity_out, 2))
            ->editColumn('balance_quantity', fn (WarehouseKardexMovement $movement) => number_format((float) $movement->balance_quantity, 2))
            ->editColumn('unit_cost', fn (WarehouseKardexMovement $movement) => $this->money($movement->unit_cost, $movement))
            ->editColumn('balance_total_cost', fn (WarehouseKardexMovement $movement) => $this->money($movement->balance_total_cost, $movement))
            ->editColumn('status', fn (WarehouseKardexMovement $movement) =>
                $this->badge($this->statusPresentation($movement->status)))
            ->addColumn('acciones', fn (WarehouseKardexMovement $movement) =>
                view('admin.kardex.partials.acciones', compact('movement'))->render())
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
                'warehouse_entry', 'warehouse_entry_cancel' => 'Ingreso de almacén',
                default => $movement->source_type ? class_basename($movement->source_type) : '-',
            },
            'source_item_label' => $movement->source_item_type ? class_basename($movement->source_item_type) : '-',
        ]);
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
            ->addColumn('article', fn (WarehouseStock $stock) =>
                trim(($stock->article?->code ? $stock->article->code . ' | ' : '') . ($stock->article?->billing_name ?? '-')))
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

        return trim($symbol . ' ' . number_format((float) $value, 2));
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
        ][$type] ?? ['label' => ucfirst((string) $type), 'class' => 'badge-light text-dark border'];
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

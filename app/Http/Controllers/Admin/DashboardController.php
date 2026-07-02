<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPurchaseOrder;
use App\Models\MarketStudy;
use App\Models\Quote;
use App\Models\SupplierPurchaseOrder;
use App\Models\WarehouseEntry;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now('America/Lima')->locale('es');

        $hour = (int) $now->format('H');
        $greeting = match (true) {
            $hour >= 5 && $hour < 12 => 'Buenos días',
            $hour >= 12 && $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        $user = auth()->user();
        $userName = $user?->name ?: ($user?->email ?: 'Usuario');
        $todayLabel = $now->translatedFormat('l, d \d\e F \d\e Y');
        $dashboardYear = $now->year;

        $metrics = [
            'totalQuotes' => $this->safeCount(Quote::class),
            'monthQuotes' => $this->safeCountWhere(Quote::class, function (Builder $query) use ($now) {
                $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
            }),
            'totalCustomerPurchaseOrders' => $this->safeCount(CustomerPurchaseOrder::class),
            'monthCustomerPurchaseOrders' => $this->safeCountWhere(CustomerPurchaseOrder::class, function (Builder $query) use ($now) {
                $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
            }),
            'totalSupplierPurchaseOrders' => $this->safeCount(SupplierPurchaseOrder::class),
            'monthSupplierPurchaseOrders' => $this->safeCountWhere(SupplierPurchaseOrder::class, function (Builder $query) use ($now) {
                $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
            }),
            'totalWarehouseEntries' => $this->safeCount(WarehouseEntry::class),
            'monthWarehouseEntries' => $this->safeCountWhere(WarehouseEntry::class, function (Builder $query) use ($now) {
                $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
            }),
            'articlesWithStock' => $this->safeCountWhere(WarehouseStock::class, function (Builder $query) {
                $query->where('current_quantity', '>', 0);
            }),
            'inventoryValue' => $this->safeSum(WarehouseStock::class, 'total_cost'),
            'lowStockItems' => $this->safeCountWhere(WarehouseStock::class, function (Builder $query) {
                $query->whereNotNull('min_stock')
                    ->whereColumn('current_quantity', '<=', 'min_stock');
            }),
            'expiringStockItems' => $this->safeCountWhere(WarehouseStock::class, function (Builder $query) use ($now) {
                $query->whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '>=', $now->toDateString())
                    ->whereDate('expiration_date', '<=', $now->copy()->addDays(30)->toDateString());
            }),
            'expiringQuotes' => $this->safeCountWhere(Quote::class, function (Builder $query) use ($now) {
                $query->whereNotNull('validity_date')
                    ->whereIn('status', Quote::EXPIRABLE_STATUSES)
                    ->whereDate('validity_date', '>=', $now->toDateString())
                    ->whereDate('validity_date', '<=', $now->copy()->addDays(7)->toDateString());
            }),
        ];

        $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $quotesChartData = $this->safeMonthlyCount(Quote::class, $now->year);
        $warehouseEntriesChartData = $this->safeMonthlySum(WarehouseEntry::class, 'grand_total', $now->year);
        $customerOrdersChartData = $this->safeMonthlyCount(CustomerPurchaseOrder::class, $now->year);
        $supplierOrdersChartData = $this->safeMonthlyCount(SupplierPurchaseOrder::class, $now->year);

        $latestQuotes = $this->safeLatest(Quote::class, ['customer', 'currency'], 4);
        $latestWarehouseEntries = $this->safeLatest(WarehouseEntry::class, ['supplier', 'warehouse', 'currency'], 4);
        $latestSupplierOrders = $this->safeLatest(SupplierPurchaseOrder::class, ['supplier', 'currency'], 4);
        $latestMarketStudies = $this->safeLatest(MarketStudy::class, [], 4);
        $latestKardexMovements = $this->safeLatest(WarehouseKardexMovement::class, ['article', 'warehouse', 'currency'], 4);

        $alerts = [
            [
                'permission' => 'admin.quotes.index',
                'icon' => 'fas fa-clock',
                'title' => number_format($metrics['expiringQuotes']) . ' cotizaciones por vencer',
                'description' => 'Vigencia dentro de los próximos 7 días.',
            ],
            [
                'permission' => 'admin.kardex.index',
                'icon' => 'fas fa-exclamation-triangle',
                'title' => number_format($metrics['lowStockItems']) . ' productos con stock bajo',
                'description' => 'Según el mínimo configurado en inventario.',
            ],
            [
                'permission' => 'admin.kardex.index',
                'icon' => 'fas fa-calendar-times',
                'title' => number_format($metrics['expiringStockItems']) . ' productos próximos a vencer',
                'description' => 'Vencimiento dentro de los próximos 30 días.',
            ],
            [
                'permission' => 'admin.supplier-purchase-orders.index',
                'icon' => 'fas fa-clipboard-check',
                'title' => number_format($metrics['monthSupplierPurchaseOrders']) . ' órdenes a proveedores este mes',
                'description' => 'Seguimiento mensual de compras para abastecimiento.',
            ],
        ];

        return view('home', compact(
            'greeting',
            'userName',
            'todayLabel',
            'dashboardYear',
            'metrics',
            'months',
            'quotesChartData',
            'warehouseEntriesChartData',
            'customerOrdersChartData',
            'supplierOrdersChartData',
            'latestQuotes',
            'latestWarehouseEntries',
            'latestSupplierOrders',
            'latestMarketStudies',
            'latestKardexMovements',
            'alerts'
        ));
    }

    private function safeCount(string $modelClass): int
    {
        return $this->safeCountWhere($modelClass);
    }

    private function safeCountWhere(string $modelClass, ?callable $callback = null): int
    {
        try {
            if (! class_exists($modelClass) || ! Schema::hasTable((new $modelClass())->getTable())) {
                return 0;
            }

            $query = $modelClass::query();

            if ($callback) {
                $callback($query);
            }

            return (int) $query->count();
        } catch (Throwable $exception) {
            return 0;
        }
    }

    private function safeSum(string $modelClass, string $column): float
    {
        try {
            if (! class_exists($modelClass)) {
                return 0;
            }

            $model = new $modelClass();

            if (! Schema::hasTable($model->getTable()) || ! Schema::hasColumn($model->getTable(), $column)) {
                return 0;
            }

            return (float) $modelClass::query()->sum($column);
        } catch (Throwable $exception) {
            return 0;
        }
    }

    private function safeMonthlyCount(string $modelClass, int $year): array
    {
        try {
            if (! class_exists($modelClass)) {
                return array_fill(0, 12, 0);
            }

            $model = new $modelClass();

            if (! Schema::hasTable($model->getTable()) || ! Schema::hasColumn($model->getTable(), 'created_at')) {
                return array_fill(0, 12, 0);
            }

            $values = $modelClass::query()
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                ->whereYear('created_at', $year)
                ->groupBy('month')
                ->pluck('total', 'month');

            return $this->fillMonthlyValues($values);
        } catch (Throwable $exception) {
            return array_fill(0, 12, 0);
        }
    }

    private function safeMonthlySum(string $modelClass, string $column, int $year): array
    {
        try {
            if (! class_exists($modelClass)) {
                return array_fill(0, 12, 0);
            }

            $model = new $modelClass();

            if (
                ! Schema::hasTable($model->getTable())
                || ! Schema::hasColumn($model->getTable(), 'created_at')
                || ! Schema::hasColumn($model->getTable(), $column)
            ) {
                return $this->safeMonthlyCount($modelClass, $year);
            }

            $values = $modelClass::query()
                ->selectRaw("MONTH(created_at) as month, COALESCE(SUM({$column}), 0) as total")
                ->whereYear('created_at', $year)
                ->groupBy('month')
                ->pluck('total', 'month');

            return $this->fillMonthlyValues($values, true);
        } catch (Throwable $exception) {
            return array_fill(0, 12, 0);
        }
    }

    private function fillMonthlyValues($values, bool $asFloat = false): array
    {
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $value = $values[$month] ?? 0;
            $data[] = $asFloat ? round((float) $value, 2) : (int) $value;
        }

        return $data;
    }

    private function safeLatest(string $modelClass, array $relations = [], int $limit = 5)
    {
        try {
            if (! class_exists($modelClass) || ! Schema::hasTable((new $modelClass())->getTable())) {
                return collect();
            }

            return $modelClass::query()
                ->with($relations)
                ->latest()
                ->limit($limit)
                ->get();
        } catch (Throwable $exception) {
            return collect();
        }
    }
}

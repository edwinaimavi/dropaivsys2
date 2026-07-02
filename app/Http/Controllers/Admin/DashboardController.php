<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPurchaseOrder;
use App\Models\MarketStudy;
use App\Models\Quote;
use App\Models\SupplierPurchaseOrder;
use App\Models\WarehouseEntry;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardController extends Controller
{
    public function index()
    {
        $metrics = [
            'totalQuotes' => $this->safeCount(Quote::class),
            'totalCustomerPurchaseOrders' => $this->safeCount(CustomerPurchaseOrder::class),
            'totalSupplierPurchaseOrders' => $this->safeCount(SupplierPurchaseOrder::class),
            'totalWarehouseEntries' => $this->safeCount(WarehouseEntry::class),
            'articlesWithStock' => $this->safeCountWhere(WarehouseStock::class, function (Builder $query) {
                $query->where('current_quantity', '>', 0);
            }),
            'inventoryValue' => $this->safeSum(WarehouseStock::class, 'total_cost'),
            'lowStockItems' => $this->safeCountWhere(WarehouseStock::class, function (Builder $query) {
                $query->whereNotNull('min_stock')
                    ->whereColumn('current_quantity', '<=', 'min_stock');
            }),
            'expiringStockItems' => $this->safeCountWhere(WarehouseStock::class, function (Builder $query) {
                $query->whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '>=', now()->toDateString())
                    ->whereDate('expiration_date', '<=', now()->addDays(30)->toDateString());
            }),
            'expiringQuotes' => $this->safeCountWhere(Quote::class, function (Builder $query) {
                $query->whereNotNull('validity_date')
                    ->whereIn('status', Quote::EXPIRABLE_STATUSES)
                    ->whereDate('validity_date', '>=', now()->toDateString())
                    ->whereDate('validity_date', '<=', now()->addDays(7)->toDateString());
            }),
        ];

        $latestQuotes = $this->safeLatest(Quote::class, ['customer', 'currency'], 4);
        $latestWarehouseEntries = $this->safeLatest(WarehouseEntry::class, ['supplier', 'warehouse', 'currency'], 4);
        $latestSupplierOrders = $this->safeLatest(SupplierPurchaseOrder::class, ['supplier', 'currency'], 4);
        $latestMarketStudies = $this->safeLatest(MarketStudy::class, [], 4);

        return view('home', compact(
            'metrics',
            'latestQuotes',
            'latestWarehouseEntries',
            'latestSupplierOrders',
            'latestMarketStudies'
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

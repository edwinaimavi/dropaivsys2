<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Models\WarehouseDispatch;
use App\Models\CustomerReturn;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseInventoryPeriodClosure;
use App\Models\WarehouseKardexRecalculation;
use App\Models\WarehouseStock;
use App\Services\WarehouseKardexService;
use App\Services\WarehouseInventoryReconciliationService;
use App\Services\WarehouseInventoryPeriodClosureService;
use App\Services\WarehousePhysicalInventoryRegisterService;
use App\Services\WarehouseValuedInventoryRegisterService;
use App\Services\WarehousePleReadinessService;
use App\Services\InventoryAccountingService;
use App\Services\ArticleInventoryPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class KardexController extends Controller
{
    public function __construct(
        private readonly ArticleInventoryPolicy $articleInventoryPolicy,
        private readonly WarehouseInventoryPeriodClosureService $periodClosureService
    )
    {
        $this->middleware('can:admin.kardex.index')->only([
            'index',
            'list',
            'articleHistory',
            'physicalInventoryRegister',
            'valuedInventoryRegister',
            'reconciliation',
            'periodClosures',
            'pleReadiness',
            'inventoryAccounting',
        ]);
        $this->middleware('can:admin.kardex.show')->only(['show']);
        $this->middleware('can:admin.kardex.stock')->only(['stock', 'stockAtDate']);
        $this->middleware('can:admin.kardex.export')->only([
            'export',
            'physicalInventoryRegisterExport',
            'valuedInventoryRegisterExport',
        ]);
        $this->middleware('can:admin.kardex.recalculate')->only([
            'recalculate',
            'closePeriod',
            'reopenPeriod',
            'storeInventoryAccountingAccount',
            'saveInventoryAccountingSettings',
            'postInventoryAccountingPeriod',
        ]);
    }

    public function index()
    {
        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);
        $articles = Article::query();
        $this->articleInventoryPolicy->scopeEligible($articles);
        $articles = $articles
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
            'inventory_value' => WarehouseStock::currentInventoryValue(),
        ];
        $lastRecalculation = WarehouseKardexRecalculation::query()
            ->with('creator:id,name,lastname')
            ->latest('started_at')
            ->first();

        return view('admin.kardex.index', compact('warehouses', 'articles', 'stats', 'lastRecalculation'));
    }

    public function physicalInventoryRegister(
        Request $request,
        WarehousePhysicalInventoryRegisterService $service
    ) {
        $user = Auth::user();
        abort_unless($user, 403);

        $companies = $user->companies()
            ->where('status', true)
            ->orderBy('business_name')
            ->get(['companies.id', 'business_name', 'ruc']);

        $companyId = $request->integer('company_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $articleId = $request->integer('article_id') ?: null;
        $year = $request->integer('year') ?: now()->year;
        $month = $request->integer('month') ?: now()->month;
        $shouldGenerate = $request->boolean('consult')
            || ($companyId && $warehouseId && $request->filled(['year', 'month']));

        if ($companyId) {
            abort_unless($companies->contains('id', $companyId), 404);
        }

        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->whereHas('companies', function (Builder $query) use ($companies, $companyId) {
                $query->whereIn('companies.id', $companyId ? [$companyId] : $companies->pluck('id'))
                    ->where('company_warehouses.is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $articles = collect();
        if ($companyId && $warehouseId) {
            $articles = WarehouseKardexMovement::query()
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->get(['article_id', 'article_code_snapshot', 'article_description_snapshot'])
                ->unique('article_id')
                ->sortBy('article_description_snapshot')
                ->values();
        }

        $report = null;
        if ($shouldGenerate) {
            $validated = $request->validate([
                'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
                'year' => ['required', 'integer', 'between:2000,2100'],
                'month' => ['required', 'integer', 'between:1,12'],
                'warehouse_id' => [
                    'required',
                    'integer',
                    Rule::exists('company_warehouses', 'warehouse_id')->where(
                        fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
                    ),
                ],
                'article_id' => ['nullable', 'integer', 'exists:articles,id'],
            ]);

            $report = $service->generate(
                (int) $validated['company_id'],
                (int) $validated['year'],
                (int) $validated['month'],
                (int) $validated['warehouse_id'],
                isset($validated['article_id']) ? (int) $validated['article_id'] : null
            );
        }

        return view('admin.kardex.formato-12-1', compact(
            'companies',
            'warehouses',
            'articles',
            'companyId',
            'warehouseId',
            'articleId',
            'year',
            'month',
            'report'
        ));
    }

    public function valuedInventoryRegister(
        Request $request,
        WarehouseValuedInventoryRegisterService $service
    ) {
        $user = Auth::user();
        abort_unless($user, 403);

        $companies = $user->companies()
            ->where('status', true)
            ->orderBy('business_name')
            ->get(['companies.id', 'business_name', 'ruc']);

        $companyId = $request->integer('company_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $articleId = $request->integer('article_id') ?: null;
        $year = $request->integer('year') ?: now()->year;
        $month = $request->integer('month') ?: now()->month;
        $shouldGenerate = $request->boolean('consult')
            || ($companyId && $warehouseId && $request->filled(['year', 'month']));

        if ($companyId) {
            abort_unless($companies->contains('id', $companyId), 404);
        }

        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->whereHas('companies', function (Builder $query) use ($companies, $companyId) {
                $query->whereIn('companies.id', $companyId ? [$companyId] : $companies->pluck('id'))
                    ->where('company_warehouses.is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $articles = collect();
        if ($companyId && $warehouseId) {
            $articles = WarehouseKardexMovement::query()
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('movement_date')
                ->orderByDesc('id')
                ->get(['article_id', 'article_code_snapshot', 'article_description_snapshot'])
                ->unique('article_id')
                ->sortBy('article_description_snapshot')
                ->values();
        }

        $report = null;
        if ($shouldGenerate) {
            $validated = $request->validate([
                'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
                'year' => ['required', 'integer', 'between:2000,2100'],
                'month' => ['required', 'integer', 'between:1,12'],
                'warehouse_id' => [
                    'required',
                    'integer',
                    Rule::exists('company_warehouses', 'warehouse_id')->where(
                        fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
                    ),
                ],
                'article_id' => ['nullable', 'integer', 'exists:articles,id'],
            ]);

            $report = $service->generate(
                (int) $validated['company_id'],
                (int) $validated['year'],
                (int) $validated['month'],
                (int) $validated['warehouse_id'],
                isset($validated['article_id']) ? (int) $validated['article_id'] : null
            );
        }

        return view('admin.kardex.formato-13-1', compact(
            'companies',
            'warehouses',
            'articles',
            'companyId',
            'warehouseId',
            'articleId',
            'year',
            'month',
            'report'
        ));
    }

    public function physicalInventoryRegisterExport(
        Request $request,
        string $format,
        WarehousePhysicalInventoryRegisterService $service
    ) {
        $validated = $this->validatedInventoryRegisterExportFilters($request, $format);
        $report = $service->generate(
            (int) $validated['company_id'],
            (int) $validated['year'],
            (int) $validated['month'],
            (int) $validated['warehouse_id'],
            isset($validated['article_id']) ? (int) $validated['article_id'] : null
        );

        return $this->inventoryRegisterExportResponse(
            'admin.kardex.exports.formato-12-1',
            'formato-12-1-inventario-fisico',
            $format,
            $report
        );
    }

    public function valuedInventoryRegisterExport(
        Request $request,
        string $format,
        WarehouseValuedInventoryRegisterService $service
    ) {
        $validated = $this->validatedInventoryRegisterExportFilters($request, $format);
        $report = $service->generate(
            (int) $validated['company_id'],
            (int) $validated['year'],
            (int) $validated['month'],
            (int) $validated['warehouse_id'],
            isset($validated['article_id']) ? (int) $validated['article_id'] : null
        );

        return $this->inventoryRegisterExportResponse(
            'admin.kardex.exports.formato-13-1',
            'formato-13-1-inventario-valorizado',
            $format,
            $report
        );
    }

    public function reconciliation(Request $request, WarehouseInventoryReconciliationService $service)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $companies = $user->companies()
            ->where('status', true)
            ->orderBy('business_name')
            ->get(['companies.id', 'business_name', 'ruc']);

        $companyId = $request->integer('company_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $articleId = $request->integer('article_id') ?: null;
        $shouldGenerate = $request->boolean('consult') || (bool) $companyId;

        if ($companyId) {
            abort_unless($companies->contains('id', $companyId), 404);
        }

        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->whereHas('companies', function (Builder $query) use ($companies, $companyId) {
                $query->whereIn('companies.id', $companyId ? [$companyId] : $companies->pluck('id'))
                    ->where('company_warehouses.is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $articles = Article::query();
        $this->articleInventoryPolicy->scopeEligible($articles);
        $articles = $articles
            ->orderBy('billing_name')
            ->get(['id', 'code', 'billing_name']);

        $report = null;
        if ($shouldGenerate) {
            $validated = $request->validate([
                'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
                'warehouse_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('company_warehouses', 'warehouse_id')->where(
                        fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
                    ),
                ],
                'article_id' => ['nullable', 'integer', 'exists:articles,id'],
            ]);

            $report = $service->audit(
                (int) $validated['company_id'],
                isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
                isset($validated['article_id']) ? (int) $validated['article_id'] : null
            );
        }

        return view('admin.kardex.reconciliation', compact(
            'companies',
            'warehouses',
            'articles',
            'companyId',
            'warehouseId',
            'articleId',
            'report'
        ));
    }

    public function pleReadiness(Request $request, WarehousePleReadinessService $service)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $companies = $user->companies()
            ->where('status', true)
            ->orderBy('business_name')
            ->get(['companies.id', 'business_name', 'ruc']);

        $companyId = $request->integer('company_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $year = $request->integer('year') ?: now()->year;
        $month = $request->integer('month') ?: now()->month;
        $year = ($year >= 2000 && $year <= 2100) ? $year : now()->year;
        $month = ($month >= 1 && $month <= 12) ? $month : now()->month;

        if ($companyId) {
            abort_unless($companies->contains('id', $companyId), 404);
        }

        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->when($companyId, function (Builder $query, int $id) {
                $query->whereHas('companies', function (Builder $companyQuery) use ($id) {
                    $companyQuery->where('companies.id', $id)
                        ->where('company_warehouses.is_active', true);
                });
            }, function (Builder $query) use ($companies) {
                $query->whereHas('companies', function (Builder $companyQuery) use ($companies) {
                    $companyQuery->whereIn('companies.id', $companies->pluck('id'))
                        ->where('company_warehouses.is_active', true);
                });
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        if ($warehouseId) {
            abort_unless($warehouses->contains('id', $warehouseId), 404);
        }

        $report = null;
        if ($request->boolean('consult') || ($companyId && $warehouseId && $request->filled(['year', 'month']))) {
            $validated = $request->validate([
                'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
                'year' => ['required', 'integer', 'between:2000,2100'],
                'month' => ['required', 'integer', 'between:1,12'],
                'warehouse_id' => [
                    'required',
                    'integer',
                    Rule::exists('company_warehouses', 'warehouse_id')->where(
                        fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
                    ),
                ],
            ]);

            $report = $service->audit(
                (int) $validated['company_id'],
                (int) $validated['year'],
                (int) $validated['month'],
                (int) $validated['warehouse_id']
            );
        }

        return view('admin.kardex.ple-readiness', compact(
            'companies',
            'warehouses',
            'companyId',
            'warehouseId',
            'year',
            'month',
            'report'
        ));
    }

    public function inventoryAccounting(Request $request, InventoryAccountingService $service)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $companies = $user->companies()
            ->where('status', true)
            ->orderBy('business_name')
            ->get(['companies.id', 'business_name', 'ruc']);

        $companyId = $request->integer('company_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $year = $request->integer('year') ?: now()->subMonthNoOverflow()->year;
        $month = $request->integer('month') ?: now()->subMonthNoOverflow()->month;
        $year = ($year >= 2000 && $year <= 2100) ? $year : now()->year;
        $month = ($month >= 1 && $month <= 12) ? $month : now()->month;

        if ($companyId) {
            abort_unless($companies->contains('id', $companyId), 404);
        }

        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->when($companyId, function (Builder $query, int $id) {
                $query->whereHas('companies', function (Builder $companyQuery) use ($id) {
                    $companyQuery->where('companies.id', $id)
                        ->where('company_warehouses.is_active', true);
                });
            }, function (Builder $query) use ($companies) {
                $query->whereHas('companies', function (Builder $companyQuery) use ($companies) {
                    $companyQuery->whereIn('companies.id', $companies->pluck('id'))
                        ->where('company_warehouses.is_active', true);
                });
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        if ($warehouseId) {
            abort_unless($warehouses->contains('id', $warehouseId), 404);
        }

        $configuration = $companyId
            ? $service->configuration($companyId)
            : ['accounts' => collect(), 'settings' => null, 'roles' => InventoryAccountingService::ROLE_LABELS];

        $report = null;
        if ($request->boolean('consult') || ($companyId && $warehouseId && $request->filled(['year', 'month']))) {
            $validated = $request->validate([
                'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
                'year' => ['required', 'integer', 'between:2000,2100'],
                'month' => ['required', 'integer', 'between:1,12'],
                'warehouse_id' => [
                    'required',
                    'integer',
                    Rule::exists('company_warehouses', 'warehouse_id')->where(
                        fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
                    ),
                ],
            ]);

            $report = $service->auditPeriod(
                (int) $validated['company_id'],
                (int) $validated['warehouse_id'],
                (int) $validated['year'],
                (int) $validated['month']
            );
        }

        return view('admin.kardex.inventory-accounting', compact(
            'companies',
            'warehouses',
            'companyId',
            'warehouseId',
            'year',
            'month',
            'configuration',
            'report'
        ));
    }

    public function storeInventoryAccountingAccount(Request $request, InventoryAccountingService $service)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $companyId = $request->integer('company_id');
        abort_unless(
            $companyId && $user->companies()->whereKey($companyId)->where('status', true)->exists(),
            404
        );

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:180'],
        ]);

        $service->createAccount(
            (int) $validated['company_id'],
            (string) $validated['code'],
            (string) $validated['name']
        );

        return redirect()->route('admin.kardex.accounting', [
            'company_id' => $validated['company_id'],
        ])->with('success', 'Cuenta contable registrada. No se asignó automáticamente a ninguna regla.');
    }

    public function saveInventoryAccountingSettings(Request $request, InventoryAccountingService $service)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $companyId = $request->integer('company_id');
        abort_unless(
            $companyId && $user->companies()->whereKey($companyId)->where('status', true)->exists(),
            404
        );

        $rules = [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
        ];
        foreach (array_keys(InventoryAccountingService::ROLE_LABELS) as $field) {
            $rules[$field] = ['nullable', 'integer', 'exists:accounting_accounts,id'];
        }

        $validated = $request->validate($rules);
        $service->saveSettings((int) $validated['company_id'], $validated, Auth::id());

        return redirect()->route('admin.kardex.accounting', [
            'company_id' => $validated['company_id'],
        ])->with('success', 'Configuración contable de inventario actualizada.');
    }

    public function postInventoryAccountingPeriod(Request $request, InventoryAccountingService $service)
    {
        $validated = $this->validatedPeriodControl($request, false);

        $result = $service->postPeriod(
            (int) $validated['company_id'],
            (int) $validated['warehouse_id'],
            (int) $validated['year'],
            (int) $validated['month'],
            Auth::id()
        );

        return redirect()->route('admin.kardex.accounting', [
            'company_id' => $validated['company_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'year' => $validated['year'],
            'month' => $validated['month'],
            'consult' => 1,
        ])->with(
            'success',
            sprintf(
                'Contabilización terminada: %d asiento(s) creado(s), %d ya existente(s).',
                $result['created_count'],
                $result['existing_count']
            )
        );
    }

    public function periodClosures(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $companies = $user->companies()
            ->where('status', true)
            ->orderBy('business_name')
            ->get(['companies.id', 'business_name', 'ruc']);

        $companyId = $request->integer('company_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $year = $request->integer('year') ?: now()->year;
        $month = $request->integer('month') ?: now()->month;
        $year = ($year >= 2000 && $year <= 2100) ? $year : now()->year;
        $month = ($month >= 1 && $month <= 12) ? $month : now()->month;

        if ($companyId) {
            abort_unless($companies->contains('id', $companyId), 404);
        }

        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->when($companyId, function (Builder $query, int $id) {
                $query->whereHas('companies', function (Builder $companyQuery) use ($id) {
                    $companyQuery->where('companies.id', $id)
                        ->where('company_warehouses.is_active', true);
                });
            }, function (Builder $query) use ($companies) {
                $query->whereHas('companies', function (Builder $companyQuery) use ($companies) {
                    $companyQuery->whereIn('companies.id', $companies->pluck('id'))
                        ->where('company_warehouses.is_active', true);
                });
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        if ($warehouseId) {
            abort_unless($warehouses->contains('id', $warehouseId), 404);
        }

        $currentState = ($companyId && $warehouseId)
            ? $this->periodClosureService->currentState($companyId, $warehouseId, $year, $month)
            : null;
        $currentState?->loadMissing('creator:id,name,lastname');

        $events = WarehouseInventoryPeriodClosure::query()
            ->with(['company:id,business_name,ruc', 'warehouse:id,code,name', 'creator:id,name,lastname'])
            ->whereIn('company_id', $companies->pluck('id'))
            ->when($companyId, fn ($query, int $id) => $query->where('company_id', $id))
            ->when($warehouseId, fn ($query, int $id) => $query->where('warehouse_id', $id))
            ->when($request->filled('year'), fn ($query) => $query->where('year', $year))
            ->when($request->filled('month'), fn ($query) => $query->where('month', $month))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('admin.kardex.period-closures', compact(
            'companies',
            'warehouses',
            'companyId',
            'warehouseId',
            'year',
            'month',
            'currentState',
            'events'
        ));
    }

    public function closePeriod(Request $request)
    {
        $validated = $this->validatedPeriodControl($request, false);

        $this->periodClosureService->close(
            (int) $validated['company_id'],
            (int) $validated['warehouse_id'],
            (int) $validated['year'],
            (int) $validated['month'],
            $validated['reason'] ?? null,
            Auth::id()
        );

        return redirect()->route('admin.kardex.period-closures', [
            'company_id' => $validated['company_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'year' => $validated['year'],
            'month' => $validated['month'],
        ])->with('success', 'Período cerrado correctamente. Los movimientos del mes quedaron protegidos.');
    }

    public function reopenPeriod(Request $request)
    {
        $validated = $this->validatedPeriodControl($request, true);

        $this->periodClosureService->reopen(
            (int) $validated['company_id'],
            (int) $validated['warehouse_id'],
            (int) $validated['year'],
            (int) $validated['month'],
            (string) $validated['reason'],
            Auth::id()
        );

        return redirect()->route('admin.kardex.period-closures', [
            'company_id' => $validated['company_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'year' => $validated['year'],
            'month' => $validated['month'],
        ])->with('success', 'Período reabierto. Las correcciones vuelven a estar permitidas hasta el próximo cierre.');
    }

    public function list(Request $request)
    {
        $movements = $this->movementQuery($request)
            ->with([
                'warehouse:id,name',
                'article:id,code,billing_name',
                'currency:id,code,symbol',
                'creator:id,name,lastname',
                'updater:id,name,lastname',
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
            ->addColumn('created_by_label', fn (WarehouseKardexMovement $movement) => $this->userLabel($movement->creator))
            ->addColumn('updated_by_label', function (WarehouseKardexMovement $movement) {
                if (! $movement->updater) {
                    return '-';
                }

                $sameInitialWrite = (int) $movement->created_by === (int) $movement->updated_by
                    && $movement->created_at?->equalTo($movement->updated_at);

                return $sameInitialWrite ? '-' : $this->userLabel($movement->updater);
            })
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
            'creator:id,name,lastname',
            'updater:id,name,lastname',
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
                'customer_order_dispatch', 'customer_order_dispatch_cancel' => 'Despacho de OC Cliente',
                'customer_return', 'customer_return_reversal' => 'Devolución cliente',
                default => $movement->source_type ? class_basename($movement->source_type) : '-',
            },
            'source_item_label' => $movement->source_item_type ? class_basename($movement->source_item_type) : '-',
            'source_url' => $movement->source_type === WarehouseEntry::class && $movement->source_id
                ? route('admin.warehouse-entries.index', [
                    'from_warehouse_entry' => $movement->source_id,
                    'auto_open' => 1,
                ])
                : ($movement->source_type === WarehouseDispatch::class && $movement->source_id
                    ? route('admin.customer-purchase-orders.index')
                    : ($movement->source_type === CustomerReturn::class && $movement->source_id
                        ? route('admin.customer-returns.index', ['return_id' => $movement->source_id])
                        : null)),
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
            ->editColumn('average_unit_cost', fn (WarehouseStock $stock) => number_format(
                (float) $stock->current_quantity > 0 ? (float) $stock->average_unit_cost : 0,
                2
            ))
            ->editColumn('total_cost', fn (WarehouseStock $stock) => number_format(
                (float) $stock->current_quantity > 0 ? (float) $stock->total_cost : 0,
                2
            ))
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

    private function validatedInventoryRegisterExportFilters(Request $request, string $format): array
    {
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);

        $user = Auth::user();
        abort_unless($user, 403);

        $companyId = $request->integer('company_id');
        $allowedCompanyIds = $user->companies()
            ->where('status', true)
            ->pluck('companies.id');

        abort_unless($companyId && $allowedCompanyIds->contains($companyId), 404);

        return $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('company_warehouses', 'warehouse_id')->where(
                    fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
                ),
            ],
            'article_id' => ['nullable', 'integer', 'exists:articles,id'],
        ]);
    }

    private function inventoryRegisterExportResponse(
        string $view,
        string $filenamePrefix,
        string $format,
        array $report
    ) {
        $filename = $filenamePrefix.'-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            return Pdf::loadView($view, ['report' => $report])
                ->setPaper('a4', 'landscape')
                ->download($filename.'.pdf');
        }

        $content = "\xEF\xBB\xBF".view($view, ['report' => $report])->render();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"',
        ]);
    }

    private function validatedPeriodControl(Request $request, bool $requireReason): array
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $companyId = $request->integer('company_id');
        abort_unless(
            $companyId && $user->companies()->whereKey($companyId)->where('status', true)->exists(),
            404
        );

        return $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', true)],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('company_warehouses', 'warehouse_id')->where(
                    fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)
                ),
            ],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'reason' => $requireReason
                ? ['required', 'string', 'min:5', 'max:2000']
                : ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function userLabel($user): string
    {
        if (! $user) {
            return '-';
        }

        return trim(($user->name ?? '').' '.($user->lastname ?? '')) ?: '-';
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

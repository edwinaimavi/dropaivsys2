<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CompanyWarehouseController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.warehouse-entries.index')->only(['index', 'warehouses']);
        $this->middleware('can:admin.warehouse-entries.update')->only(['store', 'update']);
    }

    public function index()
    {
        $companyIds = Auth::user()->companies()->pluck('companies.id');
        $relations = CompanyWarehouse::query()
            ->with(['company:id,business_name,trade_name,ruc', 'warehouse:id,code,name'])
            ->whereIn('company_id', $companyIds)
            ->orderBy('company_id')
            ->orderBy('warehouse_id')
            ->get();

        return response()->json([
            'data' => $relations,
            'companies' => Company::query()
                ->whereIn('id', $companyIds)
                ->where('status', true)
                ->orderBy('business_name')
                ->get(['id', 'business_name', 'trade_name', 'ruc']),
            'warehouses' => Warehouse::query()
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function warehouses(Company $company)
    {
        abort_unless(Auth::user()->belongsToCompany((int) $company->id), 404);

        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->whereHas('companyWarehouses', fn ($query) => $query
                ->where('company_id', $company->id)
                ->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json(['data' => $warehouses]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        abort_unless(Auth::user()->belongsToCompany((int) $validated['company_id']), 404);

        $relation = CompanyWarehouse::create([
            ...$validated,
            'created_by_user_id' => Auth::id(),
            'updated_by_user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Empresa asociada al almacén correctamente.',
            'data' => $relation->load(['company:id,business_name,trade_name,ruc', 'warehouse:id,code,name']),
        ], 201);
    }

    public function update(Request $request, CompanyWarehouse $companyWarehouse)
    {
        abort_unless(Auth::user()->belongsToCompany((int) $companyWarehouse->company_id), 404);
        $validated = $request->validate([
            'sunat_establishment_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
            ],
            'is_active' => ['required', 'boolean'],
        ], $this->messages());

        $companyWarehouse->update([
            'sunat_establishment_code' => $this->nullableCode($validated['sunat_establishment_code'] ?? null),
            'is_active' => $validated['is_active'],
            'updated_by_user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Configuración empresa–almacén actualizada correctamente.',
            'data' => $companyWarehouse->fresh([
                'company:id,business_name,trade_name,ruc',
                'warehouse:id,code,name',
            ]),
        ]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                Rule::unique('company_warehouses', 'warehouse_id')
                    ->where(fn ($query) => $query->where('company_id', $request->integer('company_id'))),
            ],
            'sunat_establishment_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
            ],
            'is_active' => ['required', 'boolean'],
        ], $this->messages());

        $validated['sunat_establishment_code'] = $this->nullableCode(
            $validated['sunat_establishment_code'] ?? null
        );

        return $validated;
    }

    private function nullableCode(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function messages(): array
    {
        return [
            'warehouse_id.unique' => 'La empresa ya está asociada con este almacén.',
            'sunat_establishment_code.regex' => 'El código de establecimiento SUNAT solo puede contener dígitos.',
            'sunat_establishment_code.max' => 'El código de establecimiento SUNAT no puede superar 20 caracteres.',
        ];
    }
}

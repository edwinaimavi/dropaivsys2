<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CompanyBankAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.company-bank-accounts.index')->only('list');
        $this->middleware('can:admin.company-bank-accounts.store')->only('store');
        $this->middleware('can:admin.company-bank-accounts.update')->only('update');
        $this->middleware('can:admin.company-bank-accounts.destroy')->only('destroy');
    }

    public function list(Company $company)
    {
        $accounts = $company->bankAccounts()
            ->with(['bank', 'currency'])
            ->latest('id');

        return DataTables::eloquent($accounts)
            ->addIndexColumn()
            ->addColumn('bank', fn (CompanyBankAccount $account) => $account->bank?->description ?? '—')
            ->addColumn('currency', fn (CompanyBankAccount $account) => $account->currency?->description ?? '—')
            ->editColumn('is_detraction', fn (CompanyBankAccount $account) => $account->is_detraction === 'YES'
                ? '<span class="badge badge-warning">SÍ</span>'
                : '<span class="badge badge-secondary">NO</span>')
            ->editColumn('status', fn (CompanyBankAccount $account) => $account->status === 'ACTIVE'
                ? '<span class="badge badge-success">ACTIVO</span>'
                : '<span class="badge badge-danger">INACTIVO</span>')
            ->addColumn('acciones', function (CompanyBankAccount $account) use ($company) {
                return view(
                    'admin.companies.bank-accounts.actions',
                    compact('company', 'account')
                )->render();
            })
            ->rawColumns(['is_detraction', 'status', 'acciones'])
            ->make(true);
    }

    public function store(Request $request, Company $company)
    {
        $validated = $this->validatedData($request);
        $validated['company_id'] = $company->id;
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        DB::transaction(fn () => CompanyBankAccount::create($validated));

        return response()->json([
            'status' => true,
            'message' => 'Cuenta bancaria registrada correctamente.',
        ], 201);
    }

    public function update(
        Request $request,
        Company $company,
        CompanyBankAccount $bankAccount
    ) {
        $this->ensureBelongsToCompany($company, $bankAccount);
        $validated = $this->validatedData($request);
        $validated['updated_by'] = Auth::id();

        DB::transaction(fn () => $bankAccount->update($validated));

        return response()->json([
            'status' => true,
            'message' => 'Cuenta bancaria actualizada correctamente.',
        ]);
    }

    public function destroy(Company $company, CompanyBankAccount $bankAccount)
    {
        $this->ensureBelongsToCompany($company, $bankAccount);

        DB::transaction(function () use ($bankAccount) {
            $bankAccount->updated_by = Auth::id();
            $bankAccount->deleted_by = Auth::id();
            $bankAccount->save();
            $bankAccount->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Cuenta bancaria eliminada correctamente.',
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'bank_id' => ['required', 'exists:banks,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'account_holder' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'cci' => ['nullable', 'string', 'max:100'],
            'is_detraction' => ['required', 'in:YES,NO'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
            'observation' => ['nullable', 'string'],
        ], [
            'bank_id.required' => 'Seleccione un banco.',
            'currency_id.required' => 'Seleccione una moneda.',
            'account_holder.required' => 'Ingrese el titular de la cuenta.',
            'account_number.required' => 'Ingrese el número de cuenta.',
        ]);
    }

    private function ensureBelongsToCompany(
        Company $company,
        CompanyBankAccount $bankAccount
    ): void {
        abort_unless($bankAccount->company_id === $company->id, 404);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SupplierAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.supplier-accounts.index')->only(['list']);
        $this->middleware('can:admin.supplier-accounts.store')->only(['store']);
        $this->middleware('can:admin.supplier-accounts.update')->only(['update']);
        $this->middleware('can:admin.supplier-accounts.destroy')->only(['destroy']);
    }

    public function list(Supplier $supplier)
    {
        $query = SupplierAccount::with([
            'bank',
            'currency'
        ])
            ->where('supplier_id', $supplier->id)
            ->orderBy('id', 'desc');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('bank', function ($item) {
                return $item->bank->description ?? '—';
            })
            ->addColumn('currency', function ($item) {
                return $item->currency->description ?? '—';
            })
            ->editColumn('is_detraction', function ($item) {
                return $item->is_detraction === 'YES'
                    ? '<span class="badge badge-warning">SÍ</span>'
                    : '<span class="badge badge-secondary">NO</span>';
            })
            ->editColumn('status', function ($item) {
                return $item->status === 'ACTIVE'
                    ? '<span class="badge badge-success">ACTIVO</span>'
                    : '<span class="badge badge-danger">INACTIVO</span>';
            })
            ->addColumn('acciones', function ($item) {
                return view('admin.suppliers.accounts.actions', compact('item'))->render();
            })
            ->rawColumns([
                'status',
                'is_detraction',
                'acciones'
            ])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([

            'supplier_id' => 'required|exists:suppliers,id',

            'bank_id' => 'required|exists:banks,id',

            'currency_id' => 'required|exists:currencies,id',

            'account_holder' => 'required|max:255',

            'account_number' => 'required|max:255',

            'cci' => 'nullable|max:255',

            'is_detraction' => 'required|in:YES,NO',

            'status' => 'required|in:ACTIVE,INACTIVE',

            'observation' => 'nullable'
        ]);

        DB::beginTransaction();

        try {

            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            SupplierAccount::create($validated);

            DB::commit();

            return response()->json([

                'status' => true,

                'message' => 'Cuenta bancaria registrada correctamente.'
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);
        }
    }

    public function update(
        Request $request,
        SupplierAccount $supplierAccount
    ) {
        $validated = $request->validate([

            'bank_id' => 'required|exists:banks,id',

            'currency_id' => 'required|exists:currencies,id',

            'account_holder' => 'required|max:255',

            'account_number' => 'required|max:255',

            'cci' => 'nullable|max:255',

            'is_detraction' => 'required|in:YES,NO',

            'status' => 'required|in:ACTIVE,INACTIVE',

            'observation' => 'nullable'
        ]);

        DB::beginTransaction();

        try {

            $validated['updated_by'] = Auth::id();

            $supplierAccount->update($validated);

            DB::commit();

            return response()->json([

                'status' => true,

                'message' => 'Cuenta bancaria actualizada correctamente.'
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);
        }
    }


    public function destroy(SupplierAccount $supplierAccount)
    {
        DB::beginTransaction();

        try {

            $supplierAccount->delete();

            DB::commit();

            return response()->json([

                'status' => true,

                'message' => 'Cuenta bancaria eliminada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class CustomerBranchController extends Controller
{
    public function list(Customer $customer)
    {
        $branches = CustomerBranch::with(['ubigeo'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_main')
            ->orderByDesc('id');

        return DataTables::eloquent($branches)
            ->addIndexColumn()

            ->addColumn('ubigeo_name', function ($branch) {
                return $branch->ubigeo->full_name ?? '—';
            })

            ->editColumn('branch_type', function ($branch) {
                return $branch->branch_type ?: '—';
            })

            ->editColumn('voucher_type', function ($branch) {
                return $branch->voucher_type ?: '—';
            })

            ->editColumn('payment_condition', function ($branch) {
                return $branch->payment_condition ?: '—';
            })

            ->addColumn('is_main_badge', function ($branch) {
                return $branch->is_main
                    ? '<span class="badge badge-success rounded-pill px-3 py-2">SI</span>'
                    : '<span class="badge badge-secondary rounded-pill px-3 py-2">NO</span>';
            })

            ->editColumn('status_badge', function ($branch) {
                return $branch->status
                    ? '<span class="badge badge-success rounded-pill px-3 py-2">ACTIVO</span>'
                    : '<span class="badge badge-secondary rounded-pill px-3 py-2">INACTIVO</span>';
            })

            ->addColumn('acciones', function ($branch) {
                return '
                    <div class="btn-group shadow-sm" role="group" aria-label="Actions">

                        <button type="button"
                            class="btn btn-outline-primary btn-sm editCustomerBranch"
                            title="Editar Sede"
                            data-id="' . $branch->id . '"
                            data-customer_id="' . $branch->customer_id . '"
                            data-branch_name="' . e($branch->branch_name) . '"
                            data-branch_type="' . e($branch->branch_type) . '"
                            data-phone="' . e($branch->phone) . '"
                            data-email="' . e($branch->email) . '"
                            data-ubigeo_id="' . ($branch->ubigeo_id ?? '') . '"
                            data-ubigeo_text="' . e($branch->ubigeo->full_name ?? '') . '"
                            data-address="' . e($branch->address) . '"
                            data-reference="' . e($branch->reference) . '"
                            data-voucher_type="' . e($branch->voucher_type) . '"
                            data-generate_guide="' . e($branch->generate_guide) . '"
                            data-payment_condition="' . e($branch->payment_condition) . '"
                            data-is_main="' . ($branch->is_main ? 1 : 0) . '"
                            data-status="' . ($branch->status ? 1 : 0) . '">

                            <i class="fas fa-pen"></i>
                        </button>

                        <button type="button"
                            class="btn btn-outline-danger btn-sm deleteCustomerBranch"
                            title="Eliminar Sede"
                            data-id="' . $branch->id . '">

                            <i class="fas fa-trash"></i>
                        </button>

                    </div>
                ';
            })

            ->rawColumns(['is_main_badge', 'status_badge', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'        => 'required|exists:customers,id',
            'branch_name'        => 'required|string|max:255',
            'branch_type'        => 'required|string|max:100',
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:255',
            'ubigeo_id'          => 'nullable|exists:ubigeos,id',
            'address'            => 'nullable|string|max:255',
            'reference'          => 'nullable|string|max:255',
            'voucher_type'       => 'required|string|max:100',
            'generate_guide'     => 'required|in:SI,NO',
            'payment_condition'  => 'required|string|max:100',
            'is_main'            => 'required|in:0,1',
            'status'             => 'required|in:0,1',
        ]);

        try {
            DB::beginTransaction();

            $data['branch_name'] = mb_strtoupper($data['branch_name']);
            $data['branch_type'] = mb_strtoupper($data['branch_type']);
            $data['address'] = $data['address'] ? mb_strtoupper($data['address']) : null;
            $data['reference'] = $data['reference'] ? mb_strtoupper($data['reference']) : null;
            $data['voucher_type'] = mb_strtoupper($data['voucher_type']);
            $data['payment_condition'] = mb_strtoupper($data['payment_condition']);
            $data['phone'] = $data['phone'] ?: null;
            $data['email'] = $data['email'] ?: null;
            $data['is_main'] = (int) $data['is_main'] === 1 ? 1 : 0;
            $data['status'] = (int) $data['status'] === 1 ? 1 : 0;
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            if ($data['is_main']) {
                CustomerBranch::where('customer_id', $data['customer_id'])
                    ->update([
                        'is_main' => 0,
                        'updated_by' => Auth::id()
                    ]);
            }

            $branch = CustomerBranch::create($data);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Sede registrada correctamente.',
                'data'    => $branch
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error creando sede: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al registrar la sede.'
            ], 500);
        }
    }

    public function update(Request $request, CustomerBranch $branch)
    {
        $data = $request->validate([
            'customer_id'        => 'required|exists:customers,id',
            'branch_name'        => 'required|string|max:255',
            'branch_type'        => 'required|string|max:100',
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:255',
            'ubigeo_id'          => 'nullable|exists:ubigeos,id',
            'address'            => 'nullable|string|max:255',
            'reference'          => 'nullable|string|max:255',
            'voucher_type'       => 'required|string|max:100',
            'generate_guide'     => 'required|in:SI,NO',
            'payment_condition'  => 'required|string|max:100',
            'is_main'            => 'required|in:0,1',
            'status'             => 'required|in:0,1',
        ]);

        try {
            DB::beginTransaction();

            $data['branch_name'] = mb_strtoupper($data['branch_name']);
            $data['branch_type'] = mb_strtoupper($data['branch_type']);
            $data['address'] = $data['address'] ? mb_strtoupper($data['address']) : null;
            $data['reference'] = $data['reference'] ? mb_strtoupper($data['reference']) : null;
            $data['voucher_type'] = mb_strtoupper($data['voucher_type']);
            $data['payment_condition'] = mb_strtoupper($data['payment_condition']);
            $data['phone'] = $data['phone'] ?: null;
            $data['email'] = $data['email'] ?: null;
            $data['is_main'] = (int) $data['is_main'] === 1 ? 1 : 0;
            $data['status'] = (int) $data['status'] === 1 ? 1 : 0;
            $data['updated_by'] = Auth::id();

            if ($data['is_main']) {
                CustomerBranch::where('customer_id', $data['customer_id'])
                    ->where('id', '!=', $branch->id)
                    ->update([
                        'is_main' => 0,
                        'updated_by' => Auth::id()
                    ]);
            }

            $branch->update($data);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Sede actualizada correctamente.',
                'data'    => $branch
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error actualizando sede: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar la sede.'
            ], 500);
        }
    }

    public function destroy(CustomerBranch $branch)
    {
        try {
            $branch->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Sede eliminada correctamente.'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error eliminando sede: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al eliminar la sede.'
            ], 500);
        }
    }

    public function branchesByCustomer(Customer $customer)
    {
        return response()->json(
            CustomerBranch::where('customer_id', $customer->id)
                ->orderBy('branch_name')
                ->get([
                    'id',
                    'branch_name'
                ])
        );
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerBranchContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class CustomerBranchContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.customer-branch-contacts.index')->only(['list', 'branchesByCustomer']);
        $this->middleware('can:admin.customer-branch-contacts.store')->only(['store']);
        $this->middleware('can:admin.customer-branch-contacts.update')->only(['update']);
        $this->middleware('can:admin.customer-branch-contacts.destroy')->only(['destroy']);
    }

    public function list(CustomerBranch $branch)
    {
        $contacts = CustomerBranchContact::query()
            ->where('customer_branch_id', $branch->id)
            ->orderByDesc('id');

        return DataTables::eloquent($contacts)
            ->addIndexColumn()

            ->editColumn('contact_name', function ($contact) {
                return $contact->contact_name ?: '—';
            })

            ->editColumn('phone', function ($contact) {
                return $contact->phone ?: '—';
            })

            ->editColumn('email', function ($contact) {
                return $contact->email ?: '—';
            })

            ->editColumn('address', function ($contact) {
                return $contact->address ?: '—';
            })

            ->editColumn('reference', function ($contact) {
                return $contact->reference ?: '—';
            })

            ->editColumn('status_badge', function ($contact) {
                return $contact->status
                    ? '<span class="badge badge-success rounded-pill px-3 py-2">ACTIVO</span>'
                    : '<span class="badge badge-secondary rounded-pill px-3 py-2">INACTIVO</span>';
            })

            ->addColumn('acciones', function ($contact) {
                return '
                    <div class="btn-group shadow-sm" role="group" aria-label="Actions">

                        <button type="button"
                            class="btn btn-outline-primary btn-sm editCustomerBranchContact"
                            title="Editar Contacto"
                            data-id="' . $contact->id . '"
                            data-customer_branch_id="' . $contact->customer_branch_id . '"
                            data-contact_name="' . e($contact->contact_name) . '"
                            data-phone="' . e($contact->phone) . '"
                            data-email="' . e($contact->email) . '"
                            data-address="' . e($contact->address) . '"
                            data-reference="' . e($contact->reference) . '"
                            data-status="' . ($contact->status ? 1 : 0) . '">

                            <i class="fas fa-pen"></i>
                        </button>

                        <button type="button"
                            class="btn btn-outline-danger btn-sm deleteCustomerBranchContact"
                            title="Eliminar Contacto"
                            data-id="' . $contact->id . '">

                            <i class="fas fa-trash"></i>
                        </button>

                    </div>
                ';
            })

            ->rawColumns(['status_badge', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_branch_id' => 'required|exists:customer_branches,id',
            'contact_name'       => 'required|string|max:255',
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:255',
            'address'            => 'nullable|string|max:255',
            'reference'          => 'nullable|string|max:255',
            'status'             => 'required|in:0,1',
        ]);

        try {
            DB::beginTransaction();

            $data['contact_name'] = mb_strtoupper($data['contact_name']);
            $data['address'] = $data['address'] ? mb_strtoupper($data['address']) : null;
            $data['reference'] = $data['reference'] ? mb_strtoupper($data['reference']) : null;
            $data['phone'] = $data['phone'] ?: null;
            $data['email'] = $data['email'] ?: null;
            $data['status'] = (int) $data['status'] === 1 ? 1 : 0;
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            $contact = CustomerBranchContact::create($data);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Contacto registrado correctamente.',
                'data'    => $contact
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error creando contacto: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al registrar el contacto.'
            ], 500);
        }
    }

    public function update(Request $request, CustomerBranchContact $contact)
    {
        $data = $request->validate([
            'customer_branch_id' => 'required|exists:customer_branches,id',
            'contact_name'       => 'required|string|max:255',
            'phone'              => 'nullable|string|max:20',
            'email'              => 'nullable|email|max:255',
            'address'            => 'nullable|string|max:255',
            'reference'          => 'nullable|string|max:255',
            'status'             => 'required|in:0,1',
        ]);

        try {
            DB::beginTransaction();

            $data['contact_name'] = mb_strtoupper($data['contact_name']);
            $data['address'] = $data['address'] ? mb_strtoupper($data['address']) : null;
            $data['reference'] = $data['reference'] ? mb_strtoupper($data['reference']) : null;
            $data['phone'] = $data['phone'] ?: null;
            $data['email'] = $data['email'] ?: null;
            $data['status'] = (int) $data['status'] === 1 ? 1 : 0;
            $data['updated_by'] = Auth::id();

            $contact->update($data);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Contacto actualizado correctamente.',
                'data'    => $contact
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error actualizando contacto: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar el contacto.'
            ], 500);
        }
    }

    public function destroy(CustomerBranchContact $contact)
    {
        try {
            $contact->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Contacto eliminado correctamente.'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error eliminando contacto: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al eliminar el contacto.'
            ], 500);
        }
    }


    public function branchesByCustomer(Customer $customer)
    {
        return response()->json(
            CustomerBranch::where(
                'customer_id',
                $customer->id
            )
                ->orderBy('branch_name')
                ->get([
                    'id',
                    'branch_name'
                ])
        );
    }
}

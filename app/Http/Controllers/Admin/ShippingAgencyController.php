<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingAgency;
use App\Models\ShippingAgencyBranch;
use App\Models\ShippingAgencyContact;
use App\Models\Ubigeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ShippingAgencyController extends Controller
{
    public function __construct()
    {

        $this->middleware('can:admin.shipping-agencies.index')->only([
            'index',
            'list',
            'consultarRuc'
        ]);
        $this->middleware('can:admin.shipping-agencies.index')->only(['index', 'list']);
        $this->middleware('can:admin.shipping-agencies.store')->only(['store']);
        $this->middleware('can:admin.shipping-agencies.update')->only(['edit', 'update']);
        $this->middleware('can:admin.shipping-agencies.destroy')->only(['destroy']);
        $this->middleware('can:admin.shipping-agencies.show')->only(['show']);
        $this->middleware('can:admin.shipping-agencies.branches')->only(['branches']);
        $this->middleware('can:admin.shipping-agencies.contacts')->only(['contacts', 'contactsByBranch']);
    }

    public function index()
    {
        $ubigeos = Ubigeo::query()
            ->orderBy('department')
            ->orderBy('province')
            ->orderBy('district')
            ->limit(3000)
            ->get();

        return view('admin.shipping-agencies.index', compact('ubigeos'));
    }

    public function list()
    {
        $agencies = ShippingAgency::query()
            ->with(['mainBranch', 'creator', 'updater'])
            ->withCount(['branches', 'contacts'])
            ->orderByDesc('id');

        return DataTables::of($agencies)
            ->addIndexColumn()
            ->editColumn('agency_type', fn(ShippingAgency $agency) => $this->agencyTypeLabel($agency->agency_type))
            ->editColumn('status', function (ShippingAgency $agency) {
                $class = $agency->status === 'ACTIVE' ? 'badge-info' : 'badge-danger';
                $label = $agency->status === 'ACTIVE' ? 'ACTIVO' : 'INACTIVO';

                return '<span class="badge ' . $class . ' text-light rounded-pill px-3 py-2 shadow-sm">' . $label . '</span>';
            })
            ->addColumn('acciones', function (ShippingAgency $agency) {
                return view('admin.shipping-agencies.partials.acciones', compact('agency'))->render();
            })
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);

        try {
            $agency = DB::transaction(function () use ($validated) {
                $agencyData = $this->prepareAgencyData($validated);
                $agencyData['code'] = $this->nextAgencyCode();
                $agencyData['created_by'] = Auth::id();
                $agencyData['updated_by'] = Auth::id();

                $agency = ShippingAgency::create($agencyData);
                $this->syncBranchesAndContacts($agency, $validated);

                return $agency;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Agencia de envio registrada correctamente.',
                'data' => $agency->fresh(['branches', 'contacts']),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error creating shipping agency: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar la agencia de envio.',
            ], 500);
        }
    }

    public function show(ShippingAgency $shippingAgency)
    {
        $shippingAgency->load([
            'branches.ubigeo',
            'contacts.branch',
            'creator',
            'updater',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $shippingAgency,
            'agency_type_label' => $this->agencyTypeLabel($shippingAgency->agency_type),
        ]);
    }

    public function edit(ShippingAgency $shippingAgency)
    {
        return $this->show($shippingAgency);
    }

    public function update(Request $request, ShippingAgency $shippingAgency)
    {
        $validated = $this->validatedData($request, $shippingAgency);

        try {
            DB::transaction(function () use ($shippingAgency, $validated) {
                $agencyData = $this->prepareAgencyData($validated);
                $agencyData['updated_by'] = Auth::id();

                $shippingAgency->update($agencyData);
                $this->syncBranchesAndContacts($shippingAgency, $validated);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Agencia de envio actualizada correctamente.',
                'data' => $shippingAgency->fresh(['branches', 'contacts']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating shipping agency: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la agencia de envio.',
            ], 500);
        }
    }

    public function destroy(ShippingAgency $shippingAgency)
    {
        try {
            DB::transaction(function () use ($shippingAgency) {
                $userId = Auth::id();

                $shippingAgency->contacts()->update(['deleted_by' => $userId]);
                $shippingAgency->branches()->update(['deleted_by' => $userId]);
                $shippingAgency->update(['deleted_by' => $userId]);

                $shippingAgency->contacts()->delete();
                $shippingAgency->branches()->delete();
                $shippingAgency->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Agencia de envio eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deleting shipping agency: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar la agencia de envio.',
            ], 500);
        }
    }

    public function branches(ShippingAgency $shippingAgency)
    {
        return response()->json([
            'branches' => $shippingAgency->activeBranches()
                ->orderByDesc('is_main')
                ->orderBy('branch_name')
                ->get(),
        ]);
    }

    public function contacts(ShippingAgency $shippingAgency)
    {
        return response()->json([
            'contacts' => $shippingAgency->activeContacts()
                ->with('branch:id,branch_name')
                ->orderByDesc('is_primary')
                ->orderBy('contact_name')
                ->get(),
        ]);
    }

    public function contactsByBranch(ShippingAgencyBranch $branch)
    {
        return response()->json([
            'contacts' => ShippingAgencyContact::query()
                ->where('shipping_agency_id', $branch->shipping_agency_id)
                ->where(function ($query) use ($branch) {
                    $query->where('shipping_agency_branch_id', $branch->id)
                        ->orWhereNull('shipping_agency_branch_id');
                })
                ->where('status', 'ACTIVE')
                ->orderByDesc('is_primary')
                ->orderBy('contact_name')
                ->get(),
        ]);
    }


    public function consultarRuc($numero)
    {
        if (!preg_match('/^\d+$/', $numero)) {
            return response()->json([
                'status'  => false,
                'message' => 'El RUC debe contener solo dígitos.'
            ], 422);
        }

        if (strlen($numero) !== 11) {
            return response()->json([
                'status'  => false,
                'message' => 'El RUC debe tener 11 dígitos.'
            ], 422);
        }

        if (!str_starts_with($numero, '10') && !str_starts_with($numero, '20')) {
            return response()->json([
                'status'  => false,
                'message' => 'El RUC debe iniciar con 10 o 20.'
            ], 422);
        }

        $token = env('APIS_PERU_TOKEN', 'apis-token-7645.70qIyk7rGHUBVYCLNlcITcM1fo-mBqvp');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.apis.net.pe/v2/sunat/ruc?numero=' . $numero,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);

            return response()->json([
                'status'  => false,
                'message' => 'Error al conectar con el servicio de RUC.',
                'error'   => $error,
            ], 500);
        }

        curl_close($curl);

        $empresa = json_decode($response);

        if (
            !$empresa ||
            isset($empresa->error) ||
            (
                empty($empresa->nombre) &&
                empty($empresa->razonSocial)
            )
        ) {
            return response()->json([
                'status'  => false,
                'message' => 'El RUC ingresado no existe.',
                'data'    => $empresa,
            ], 404);
        }

        $razonSocial = $empresa->nombre
            ?? $empresa->razonSocial
            ?? '';

        $direccion = trim(
            ($empresa->direccion ?? $empresa->domicilioFiscal ?? '') .
                (
                    !empty($empresa->distrito) ||
                    !empty($empresa->provincia) ||
                    !empty($empresa->departamento)
                    ? ' - ' . collect([
                        $empresa->distrito ?? null,
                        $empresa->provincia ?? null,
                        $empresa->departamento ?? null,
                    ])->filter()->implode(' - ')
                    : ''
                )
        );

        return response()->json([
            'status'       => true,
            'type'         => 'RUC',
            'data'         => $empresa,
            'razon_social' => mb_strtoupper($razonSocial),
            'direccion'    => mb_strtoupper($direccion),
            'departamento' => mb_strtoupper($empresa->departamento ?? ''),
            'provincia'    => mb_strtoupper($empresa->provincia ?? ''),
            'distrito'     => mb_strtoupper($empresa->distrito ?? ''),
        ]);
    }

    private function validatedData(Request $request, ?ShippingAgency $agency = null): array
    {
        return $request->validate([
            'ruc' => [
                'nullable',
                'digits:11',
                Rule::unique('shipping_agencies', 'ruc')
                    ->ignore($agency?->id)
                    ->whereNull('deleted_at'),
            ],
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'agency_type' => ['required', Rule::in($this->agencyTypeOptions())],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'observations' => ['nullable', 'string'],
            'branches' => ['nullable', 'array'],
            'branches.*.branch_name' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.address' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.ubigeo_id' => ['nullable', 'exists:ubigeos,id'],
            'branches.*.department' => ['nullable', 'string', 'max:120'],
            'branches.*.province' => ['nullable', 'string', 'max:120'],
            'branches.*.district' => ['nullable', 'string', 'max:120'],
            'branches.*.reference' => ['nullable', 'string', 'max:255'],
            'branches.*.is_main' => ['nullable', 'boolean'],
            'branches.*.phone' => ['nullable', 'string', 'max:30'],
            'branches.*.email' => ['nullable', 'email', 'max:255'],
            'branches.*.status' => ['required_with:branches', Rule::in(['ACTIVE', 'INACTIVE'])],
            'branches.*.observations' => ['nullable', 'string'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.branch_index' => ['nullable', 'integer', 'min:0'],
            'contacts.*.contact_name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.whatsapp' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'contacts.*.status' => ['required_with:contacts', Rule::in(['ACTIVE', 'INACTIVE'])],
            'contacts.*.observations' => ['nullable', 'string'],
        ], [
            'ruc.unique' => 'El RUC ya esta registrado.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'business_name.required' => 'La razon social es obligatoria.',
            'agency_type.required' => 'El tipo de agencia es obligatorio.',
            'agency_type.in' => 'El tipo de agencia seleccionado no es valido.',
            'email.email' => 'El correo no tiene un formato valido.',
            'status.required' => 'El estado es obligatorio.',
            'branches.*.branch_name.required_with' => 'El nombre de la sede es obligatorio.',
            'branches.*.address.required_with' => 'La direccion de la sede es obligatoria.',
            'branches.*.email.email' => 'El correo de la sede no tiene un formato valido.',
            'branches.*.status.required_with' => 'El estado de la sede es obligatorio.',
            'contacts.*.contact_name.required_with' => 'El nombre del contacto es obligatorio.',
            'contacts.*.email.email' => 'El correo no tiene un formato valido.',
            'contacts.*.status.required_with' => 'El estado del contacto es obligatorio.',
        ]);
    }

    private function prepareAgencyData(array $validated): array
    {
        return [
            'ruc' => $this->upperOrNull($validated['ruc'] ?? null),
            'business_name' => $this->upperOrNull($validated['business_name']),
            'trade_name' => $this->upperOrNull($validated['trade_name'] ?? null),
            'agency_type' => $validated['agency_type'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'website' => $validated['website'] ?? null,
            'status' => $validated['status'],
            'observations' => $this->upperOrNull($validated['observations'] ?? null),
        ];
    }

    private function syncBranchesAndContacts(ShippingAgency $agency, array $validated): void
    {
        $userId = Auth::id();
        $oldBranchIds = $agency->branches()->pluck('id');
        $oldContactIds = $agency->contacts()->pluck('id');

        if ($oldContactIds->isNotEmpty()) {
            ShippingAgencyContact::whereIn('id', $oldContactIds)->update(['deleted_by' => $userId]);
            ShippingAgencyContact::whereIn('id', $oldContactIds)->delete();
        }

        if ($oldBranchIds->isNotEmpty()) {
            ShippingAgencyBranch::whereIn('id', $oldBranchIds)->update(['deleted_by' => $userId]);
            ShippingAgencyBranch::whereIn('id', $oldBranchIds)->delete();
        }

        $branchMap = [];
        $mainAssigned = false;

        foreach (($validated['branches'] ?? []) as $index => $branchData) {
            $isMain = !$mainAssigned && !empty($branchData['is_main']);
            $mainAssigned = $mainAssigned || $isMain;

            $branchMap[$index] = $agency->branches()->create([
                'code' => $this->nextBranchCode(),
                'branch_name' => $this->upperOrNull($branchData['branch_name']),
                'address' => $this->upperOrNull($branchData['address']),
                'ubigeo_id' => $branchData['ubigeo_id'] ?? null,
                'department' => $this->upperOrNull($branchData['department'] ?? null),
                'province' => $this->upperOrNull($branchData['province'] ?? null),
                'district' => $this->upperOrNull($branchData['district'] ?? null),
                'reference' => $this->upperOrNull($branchData['reference'] ?? null),
                'is_main' => $isMain,
                'phone' => $branchData['phone'] ?? null,
                'email' => $branchData['email'] ?? null,
                'status' => $branchData['status'] ?? 'ACTIVE',
                'observations' => $this->upperOrNull($branchData['observations'] ?? null),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        foreach (($validated['contacts'] ?? []) as $contactData) {
            $branchIndex = $contactData['branch_index'] ?? null;
            $branch = is_numeric($branchIndex) ? ($branchMap[(int) $branchIndex] ?? null) : null;

            $agency->contacts()->create([
                'shipping_agency_branch_id' => $branch?->id,
                'contact_name' => $this->upperOrNull($contactData['contact_name']),
                'position' => $this->upperOrNull($contactData['position'] ?? null),
                'phone' => $contactData['phone'] ?? null,
                'whatsapp' => $contactData['whatsapp'] ?? null,
                'email' => $contactData['email'] ?? null,
                'is_primary' => !empty($contactData['is_primary']),
                'status' => $contactData['status'] ?? 'ACTIVE',
                'observations' => $this->upperOrNull($contactData['observations'] ?? null),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }


    private function nextAgencyCode(): string
    {
        return $this->nextCode(ShippingAgency::withTrashed(), 'AGE-', 6);
    }

    private function nextBranchCode(): string
    {
        return $this->nextCode(ShippingAgencyBranch::withTrashed(), 'SUC-', 6);
    }

    private function nextCode($query, string $prefix, int $digits): string
    {
        $last = (clone $query)->where('code', 'like', $prefix . '%')
            ->pluck('code')
            ->map(fn(?string $code) => preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $code, $matches)
                ? (int) $matches[1]
                : 0)
            ->max() ?? 0;

        do {
            $last++;
            $code = $prefix . str_pad((string) $last, $digits, '0', STR_PAD_LEFT);
        } while ((clone $query)->where('code', $code)->exists());

        return $code;
    }

    private function agencyTypeOptions(): array
    {
        return ['TRANSPORTISTA', 'COURIER', 'CARGA', 'AEREO', 'TERRESTRE', 'MIXTO', 'OTRO'];
    }

    private function agencyTypeLabel(?string $value): string
    {
        return [
            'TRANSPORTISTA' => 'Transportista',
            'COURIER' => 'Courier',
            'CARGA' => 'Carga',
            'AEREO' => 'Aereo',
            'TERRESTRE' => 'Terrestre',
            'MIXTO' => 'Mixto',
            'OTRO' => 'Otro',
        ][$value] ?? ($value ?: '-');
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_strtoupper($value) : null;
    }

    //CONSULTAR RUC 
}

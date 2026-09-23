<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ElectronicInvoiceCatalogController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:catalogos_sunat.ver')->only(['index', 'catalogs', 'items', 'list']);
        $this->middleware('can:catalogos_sunat.crear')->only(['storeCatalog', 'storeItem']);
        $this->middleware('can:catalogos_sunat.editar')->only(['updateCatalog', 'updateItem']);
        $this->middleware('can:catalogos_sunat.cambiar_estado')->only(['updateCatalogStatus', 'updateItemStatus']);
    }

    public function index()
    {
        return view('admin.sunat-catalogs.index');
    }

    public function catalogs(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));

        $catalogs = SunatCatalog::query()
            ->withCount([
                'items',
                'items as active_items_count' => fn ($query) => $query->where('status', 'ACTIVE'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->limit(100)
            ->get();

        return response()->json(['data' => $catalogs]);
    }

    /**
     * Endpoint plano legacy conservado para integraciones existentes.
     */
    public function list()
    {
        return DataTables::of(
            SunatCatalogItem::query()->orderBy('catalog_code')->orderBy('item_code')
        )
            ->editColumn('status', fn (SunatCatalogItem $item) => $item->status === 'ACTIVE'
                ? '<span class="badge badge-success">Activo</span>'
                : '<span class="badge badge-secondary">Inactivo</span>')
            ->rawColumns(['status'])
            ->make(true);
    }

    public function items(SunatCatalog $sunatCatalog)
    {
        $query = $sunatCatalog->items()
            ->select('sunat_catalog_items.*')
            ->orderBy('item_code');

        return DataTables::of($query)
            ->addColumn('is_active', fn (SunatCatalogItem $item) => $item->status === 'ACTIVE')
            ->addColumn('source_label', fn (SunatCatalogItem $item) => match ($item->source) {
                'sunat_pdf' => 'SUNAT PDF',
                'manual' => 'Manual',
                default => $item->source ?: 'Sin especificar',
            })
            ->make(true);
    }

    public function storeCatalog(Request $request): JsonResponse
    {
        $data = $this->validateCatalog($request);
        $data['source'] = 'manual';
        $data['is_active'] = true;
        $data['created_by_user_id'] = $request->user()->id;
        $data['updated_by_user_id'] = $request->user()->id;

        $catalog = SunatCatalog::create($data);

        return response()->json([
            'message' => 'Catálogo SUNAT creado correctamente.',
            'data' => $catalog,
        ], 201);
    }

    public function updateCatalog(Request $request, SunatCatalog $sunatCatalog): JsonResponse
    {
        $data = $this->validateCatalog($request, $sunatCatalog);
        $data['updated_by_user_id'] = $request->user()->id;

        DB::transaction(function () use ($sunatCatalog, $data) {
            $previousCode = $sunatCatalog->code;
            $sunatCatalog->update($data);

            if ($previousCode !== $sunatCatalog->code) {
                $sunatCatalog->items()->update(['catalog_code' => $sunatCatalog->code]);
            }
        });

        return response()->json([
            'message' => 'Catálogo SUNAT actualizado correctamente.',
            'data' => $sunatCatalog->fresh(),
        ]);
    }

    public function updateCatalogStatus(Request $request, SunatCatalog $sunatCatalog): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ], [
            'is_active.required' => 'Debe indicar el nuevo estado del catálogo.',
            'is_active.boolean' => 'El estado indicado no es válido.',
        ]);

        $sunatCatalog->update([
            'is_active' => $data['is_active'],
            'updated_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => $sunatCatalog->is_active ? 'Catálogo activado correctamente.' : 'Catálogo inactivado correctamente.',
        ]);
    }

    public function storeItem(Request $request, SunatCatalog $sunatCatalog): JsonResponse
    {
        $data = $this->validateItem($request, $sunatCatalog);
        $data += [
            'sunat_catalog_id' => $sunatCatalog->id,
            'catalog_code' => $sunatCatalog->code,
            'source' => 'manual',
            'is_official' => false,
            'status' => 'ACTIVE',
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ];

        $item = SunatCatalogItem::create($data);

        return response()->json([
            'message' => 'Código SUNAT agregado correctamente.',
            'data' => $item,
        ], 201);
    }

    public function updateItem(
        Request $request,
        SunatCatalog $sunatCatalog,
        SunatCatalogItem $sunatCatalogItem
    ): JsonResponse {
        $this->ensureItemBelongsToCatalog($sunatCatalog, $sunatCatalogItem);
        $data = $this->validateItem($request, $sunatCatalog, $sunatCatalogItem);
        $data['catalog_code'] = $sunatCatalog->code;
        $data['updated_by_user_id'] = $request->user()->id;

        $sunatCatalogItem->update($data);

        return response()->json([
            'message' => 'Código SUNAT actualizado correctamente.',
            'data' => $sunatCatalogItem->fresh(),
        ]);
    }

    public function updateItemStatus(
        Request $request,
        SunatCatalog $sunatCatalog,
        SunatCatalogItem $sunatCatalogItem
    ): JsonResponse {
        $this->ensureItemBelongsToCatalog($sunatCatalog, $sunatCatalogItem);
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ], [
            'is_active.required' => 'Debe indicar el nuevo estado del código.',
            'is_active.boolean' => 'El estado indicado no es válido.',
        ]);

        $sunatCatalogItem->update([
            'status' => $data['is_active'] ? 'ACTIVE' : 'INACTIVE',
            'updated_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => $data['is_active'] ? 'Código activado correctamente.' : 'Código inactivado correctamente.',
        ]);
    }

    private function validateCatalog(Request $request, ?SunatCatalog $catalog = null): array
    {
        $request->merge([
            'code' => mb_strtoupper(trim((string) $request->input('code')), 'UTF-8'),
            'name' => mb_strtoupper(trim((string) $request->input('name')), 'UTF-8'),
            'description' => trim((string) $request->input('description')) ?: null,
        ]);

        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('sunat_catalogs', 'code')->ignore($catalog?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'code.required' => 'El código del catálogo es obligatorio.',
            'code.unique' => 'Ya existe un catálogo con ese código.',
            'code.max' => 'El código no debe superar 50 caracteres.',
            'name.required' => 'El nombre del catálogo es obligatorio.',
            'name.max' => 'El nombre no debe superar 255 caracteres.',
            'description.max' => 'La descripción no debe superar 2000 caracteres.',
        ]);
    }

    private function validateItem(
        Request $request,
        SunatCatalog $catalog,
        ?SunatCatalogItem $item = null
    ): array {
        $request->merge([
            'item_code' => mb_strtoupper(trim((string) $request->input('item_code')), 'UTF-8'),
            'description' => mb_strtoupper(trim((string) $request->input('description')), 'UTF-8'),
            'short_name' => trim((string) $request->input('short_name')) ?: null,
        ]);

        return $request->validate([
            'item_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('sunat_catalog_items', 'item_code')
                    ->where(fn ($query) => $query->where('catalog_code', $catalog->code))
                    ->ignore($item?->id),
            ],
            'description' => ['required', 'string', 'max:5000'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'extra_data' => ['nullable', 'array'],
        ], [
            'item_code.required' => 'El código SUNAT es obligatorio.',
            'item_code.unique' => 'Ese código ya existe dentro del catálogo seleccionado.',
            'item_code.max' => 'El código no debe superar 20 caracteres.',
            'description.required' => 'La descripción es obligatoria.',
            'description.max' => 'La descripción no debe superar 5000 caracteres.',
            'short_name.max' => 'El nombre corto no debe superar 255 caracteres.',
            'extra_data.array' => 'Los datos adicionales deben tener una estructura válida.',
        ]);
    }

    private function ensureItemBelongsToCatalog(SunatCatalog $catalog, SunatCatalogItem $item): void
    {
        abort_unless($item->sunat_catalog_id === $catalog->id, 404);
    }
}

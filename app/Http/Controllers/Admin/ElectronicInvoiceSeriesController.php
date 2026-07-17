<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ElectronicInvoiceSeries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class ElectronicInvoiceSeriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.electronic-invoice-series.index')->only(['index', 'list', 'getNextNumber']);
        $this->middleware('can:admin.electronic-invoice-series.show')->only(['show']);
        $this->middleware('can:admin.electronic-invoice-series.store')->only(['store']);
        $this->middleware('can:admin.electronic-invoice-series.update')->only(['update']);
        $this->middleware('can:admin.electronic-invoice-series.destroy')->only(['destroy']);
    }

    public function index()
    {
        $companies = Company::query()->where('status', true)->orderBy('business_name')->get();

        return view('admin.electronic-invoice-series.index', compact('companies'));
    }

    public function list()
    {
        $table = (new ElectronicInvoiceSeries())->getTable();

        $seriesQuery = ElectronicInvoiceSeries::query()
            ->leftJoin('companies', 'companies.id', '=', "{$table}.company_id")
            ->select([
                "{$table}.*",
                'companies.id as company_table_id',
                'companies.business_name as company_business_name',
                'companies.trade_name as company_trade_name',
            ])
            ->orderByDesc("{$table}.id");

        return DataTables::eloquent($seriesQuery)
            ->addColumn('company', fn (ElectronicInvoiceSeries $series) =>
                $series->company_trade_name ?? $series->company_business_name ?? '-')
            ->addColumn('document_type_label', fn (ElectronicInvoiceSeries $series) => $this->documentTypeLabel($series->document_type))
            ->editColumn('status', fn (ElectronicInvoiceSeries $series) =>
                $series->status === 'ACTIVE'
                    ? '<span class="badge badge-success rounded-pill px-3">Activo</span>'
                    : '<span class="badge badge-secondary rounded-pill px-3">Inactivo</span>')
            ->addColumn('acciones', function (ElectronicInvoiceSeries $series) {
                $buttons = '<div class="btn-group" role="group">';

                if (auth()->user()?->can('admin.electronic-invoice-series.show')) {
                    $buttons .= '<button class="btn btn-sm btn-outline-info viewElectronicInvoiceSeries" data-id="' . $series->id . '" title="Ver"><i class="fas fa-eye"></i></button>';
                }

                if (auth()->user()?->can('admin.electronic-invoice-series.update')) {
                    $buttons .= '<button class="btn btn-sm btn-outline-primary editElectronicInvoiceSeries" data-id="' . $series->id . '" title="Editar"><i class="fas fa-edit"></i></button>';
                }

                if (auth()->user()?->can('admin.electronic-invoice-series.destroy')) {
                    $buttons .= '<button class="btn btn-sm btn-outline-danger deleteElectronicInvoiceSeries" data-id="' . $series->id . '" title="Eliminar"><i class="fas fa-trash"></i></button>';
                }

                return $buttons . '</div>';
            })
            ->orderColumn('id', "{$table}.id $1")
            ->orderColumn('company', 'companies.business_name $1')
            ->orderColumn('document_type_label', "{$table}.document_type $1")
            ->orderColumn('serie', "{$table}.serie $1")
            ->orderColumn('current_number', "{$table}.current_number $1")
            ->orderColumn('next_number', "{$table}.next_number $1")
            ->orderColumn('environment', "{$table}.environment $1")
            ->orderColumn('status', "{$table}.status $1")
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function show(ElectronicInvoiceSeries $electronicInvoiceSeries)
    {
        return response()->json([
            'status' => 'success',
            'data' => $electronicInvoiceSeries->load('company'),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, ElectronicInvoiceSeries $electronicInvoiceSeries)
    {
        return $this->save($request, $electronicInvoiceSeries);
    }

    public function destroy(ElectronicInvoiceSeries $electronicInvoiceSeries)
    {
        $electronicInvoiceSeries->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Serie eliminada correctamente.',
        ]);
    }

    public function getNextNumber(Request $request)
    {
        $validated = $request->validate([
            'serie_id' => ['required', 'exists:electronic_invoice_series,id'],
        ]);

        $series = ElectronicInvoiceSeries::findOrFail($validated['serie_id']);

        return response()->json([
            'status' => 'success',
            'serie' => $series->serie,
            'next_number' => str_pad((string) $series->next_number, 8, '0', STR_PAD_LEFT),
            'full_number' => $series->serie . '-' . str_pad((string) $series->next_number, 8, '0', STR_PAD_LEFT),
        ]);
    }

    private function save(Request $request, ?ElectronicInvoiceSeries $series = null)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'document_type' => ['required', Rule::in(['01', '03', '07', '08'])],
            'serie' => ['required', 'string', 'max:10'],
            'current_number' => ['nullable', 'integer', 'min:0'],
            'next_number' => ['required', 'integer', 'min:1'],
            'environment' => ['required', Rule::in(['beta', 'production'])],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ], [
            'company_id.required' => 'Debe seleccionar una empresa.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'document_type.required' => 'Debe seleccionar el tipo de documento.',
            'document_type.in' => 'El tipo de documento seleccionado no es válido.',
            'serie.required' => 'La serie es obligatoria.',
            'serie.max' => 'La serie no debe superar 10 caracteres.',
            'current_number.integer' => 'El número actual debe ser entero.',
            'current_number.min' => 'El número actual no puede ser negativo.',
            'next_number.required' => 'El número siguiente es obligatorio.',
            'next_number.integer' => 'El número siguiente debe ser entero.',
            'next_number.min' => 'El número siguiente debe ser mayor a cero.',
            'environment.required' => 'Debe seleccionar el ambiente.',
            'environment.in' => 'El ambiente seleccionado no es válido.',
            'status.required' => 'Debe seleccionar el estado.',
            'status.in' => 'El estado seleccionado no es válido.',
        ]);

        $exists = ElectronicInvoiceSeries::query()
            ->where('company_id', $validated['company_id'])
            ->where('document_type', $validated['document_type'])
            ->where('serie', mb_strtoupper($validated['serie']))
            ->where('environment', $validated['environment'])
            ->when($series, fn ($query) => $query->whereKeyNot($series->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'serie' => 'Ya existe una serie activa o registrada con la misma empresa, tipo y ambiente.',
            ]);
        }

        $prefix = $validated['document_type'] === '01' ? 'F'
            : ($validated['document_type'] === '03' ? 'B' : null);
        if ($prefix && ! str_starts_with(mb_strtoupper($validated['serie']), $prefix)) {
            throw ValidationException::withMessages([
                'serie' => "La serie para este tipo de comprobante debe comenzar con {$prefix}.",
            ]);
        }

        try {
            $data = array_merge($validated, [
                'serie' => mb_strtoupper($validated['serie']),
                'current_number' => (int) ($validated['current_number'] ?? 0),
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'updated_by' => Auth::id(),
            ]);

            if ($series) {
                $series->update($data);
            } else {
                $data['created_by'] = Auth::id();
                $series = ElectronicInvoiceSeries::create($data);
            }

            if ($series->is_default) {
                ElectronicInvoiceSeries::query()
                    ->where('company_id', $series->company_id)
                    ->where('document_type', $series->document_type)
                    ->where('environment', $series->environment)
                    ->whereKeyNot($series->id)
                    ->update(['is_default' => false, 'updated_by' => Auth::id()]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Serie guardada correctamente.',
                'data' => $series,
            ], $series->wasRecentlyCreated ? 201 : 200);
        } catch (\Throwable $e) {
            Log::error('Error saving electronic invoice series: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar la serie.',
            ], 500);
        }
    }

    private function documentTypeLabel(string $type): string
    {
        return ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de Credito', '08' => 'Nota de Debito'][$type] ?? $type;
    }
}

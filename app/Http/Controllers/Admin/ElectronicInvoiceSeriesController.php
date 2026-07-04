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
        $this->middleware('can:admin.electronic-invoice-series.index')->only(['index', 'list', 'show', 'getNextNumber']);
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
        return DataTables::of(ElectronicInvoiceSeries::query()->with('company')->orderByDesc('id'))
            ->addColumn('company', fn (ElectronicInvoiceSeries $series) =>
                $series->company?->trade_name ?? $series->company?->business_name ?? '-')
            ->addColumn('document_type_label', fn (ElectronicInvoiceSeries $series) => $this->documentTypeLabel($series->document_type))
            ->editColumn('status', fn (ElectronicInvoiceSeries $series) =>
                $series->status === 'ACTIVE'
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-secondary">Inactivo</span>')
            ->addColumn('acciones', fn (ElectronicInvoiceSeries $series) =>
                '<button class="btn btn-sm btn-outline-primary editElectronicInvoiceSeries" data-id="' . $series->id . '"><i class="fas fa-edit"></i></button>
                 <button class="btn btn-sm btn-outline-danger deleteElectronicInvoiceSeries" data-id="' . $series->id . '"><i class="fas fa-trash"></i></button>')
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function show(ElectronicInvoiceSeries $electronicInvoiceSeries)
    {
        return response()->json(['status' => 'success', 'data' => $electronicInvoiceSeries]);
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

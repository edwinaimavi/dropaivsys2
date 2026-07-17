<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerPurchaseOrder;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceApiLog;
use App\Models\ElectronicInvoiceSeries;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Quote;
use App\Models\SunatCatalogItem;
use App\Models\WarehouseEntry;
use App\Models\Warehouse;
use App\Services\WarehouseKardexService;
use App\Services\ElectronicBilling\ApiPeruBillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class ElectronicInvoiceController extends Controller
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_GENERATED = 'generated';

    public function __construct()
    {
        $this->middleware('can:admin.electronic-invoices.index')->only(['index', 'list', 'customerPurchaseOrderData']);
        $this->middleware('can:admin.electronic-invoices.store')->only(['store']);
        $this->middleware('can:admin.electronic-invoices.show')->only(['show']);
        $this->middleware('can:admin.electronic-invoices.update')->only(['update']);
        $this->middleware('can:admin.electronic-invoices.destroy')->only(['destroy']);
        $this->middleware('can:admin.electronic-invoices.pdf')->only(['pdf']);
        $this->middleware('can:admin.electronic-invoices.payload')->only(['previewPayload']);
        $this->middleware('can:admin.electronic-invoices.send')->only(['sendToApi']);
    }

    public function index()
    {
        $companies = Company::query()->where('status', true)->orderBy('business_name')->get();
        $customers = Customer::query()->where('status', true)->orderBy('business_name')->orderBy('full_name')->get();
        $customerBranches = CustomerBranch::query()
            ->where('status', true)
            ->orderByDesc('is_main')
            ->orderBy('branch_name')
            ->get(['id', 'customer_id', 'branch_name', 'address']);
        $currencies = Currency::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $series = ElectronicInvoiceSeries::query()
            ->with('company:id,business_name,trade_name')
            ->where('status', 'ACTIVE')
            ->orderBy('document_type')
            ->orderBy('serie')
            ->get();
        $companyEnvironments = ElectronicInvoiceSetting::query()
            ->where('is_active', true)->whereNotNull('company_id')
            ->orderByRaw("CASE WHEN environment = 'internal' THEN 0 WHEN environment = 'beta' THEN 1 ELSE 2 END")
            ->get(['company_id', 'environment'])->groupBy('company_id')
            ->map(fn ($settings) => $settings->first()->environment);
        $articles = Article::query()
            ->where('status', 'ACTIVE')
            ->orderBy('billing_name')
            ->get(['id', 'code', 'billing_name', 'commercial_name', 'unit_id', 'presentation_id', 'brand_id']);
        $quotes = Quote::query()->orderByDesc('id')->limit(300)->get(['id', 'quote_number', 'customer_id']);
        $customerPurchaseOrders = CustomerPurchaseOrder::query()
            ->whereIn('status', ['partial_entered', 'entered', 'delivered'])
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'code', 'purchase_order_number', 'quote_id', 'customer_id', 'customer_branch_id', 'siaf_file_number', 'process_type']);
        $warehouses = Warehouse::query()->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'code', 'name']);
        $warehouseEntries = WarehouseEntry::query()
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'entry_number', 'supplier_id', 'grand_total']);
        $taxAffectations = SunatCatalogItem::query()
            ->where('catalog_code', 'tax_affectation')
            ->where('status', 'ACTIVE')
            ->orderBy('item_code')
            ->get();

        return view('admin.electronic-invoices.index', compact(
            'companies',
            'customers',
            'customerBranches',
            'currencies',
            'series',
            'companyEnvironments',
            'articles',
            'quotes',
            'customerPurchaseOrders',
            'warehouseEntries',
            'warehouses',
            'taxAffectations'
        ));
    }

    public function customerPurchaseOrderData(CustomerPurchaseOrder $customerPurchaseOrder)
    {
        if (! in_array($customerPurchaseOrder->status, ['partial_entered', 'entered', 'delivered'], true)) {
            return response()->json(['message' => 'La orden seleccionada todavía no está disponible para facturación.'], 422);
        }

        $customerPurchaseOrder->load([
            'customer', 'customerBranch', 'currency',
            'items' => fn ($query) => $query->where('status', '!=', 'deleted')
                ->with(['article', 'unit', 'presentation', 'brand']),
        ]);

        return response()->json([
            'data' => [
                'id' => $customerPurchaseOrder->id,
                'customer_id' => $customerPurchaseOrder->customer_id,
                'customer_branch_id' => $customerPurchaseOrder->customer_branch_id,
                'quote_id' => $customerPurchaseOrder->quote_id,
                'currency_id' => $customerPurchaseOrder->currency_id,
                'purchase_order_number' => $customerPurchaseOrder->purchase_order_number ?: $customerPurchaseOrder->code,
                'siaf_number' => $customerPurchaseOrder->siaf_file_number,
                'process_number' => $customerPurchaseOrder->process_type,
                'items' => $customerPurchaseOrder->items->map(fn ($item) => [
                    'customer_purchase_order_item_id' => $item->id,
                    'article_id' => $item->article_id,
                    'product_code' => $item->article_code ?: $item->article?->code,
                    'description' => $item->billing_name_snapshot ?: $item->article?->billing_name,
                    'brand_name' => $item->brand?->description,
                    'presentation_name' => $item->presentation?->description,
                    'unit_code' => $item->unit?->abbreviation ?: 'NIU',
                    'origin' => $item->origin,
                    'expiration_date' => optional($item->expiration_date)->format('Y-m-d'),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_affectation_code' => $customerPurchaseOrder->affect_igv ? '10' : '20',
                ])->values(),
            ],
        ]);
    }

    public function list()
    {
        $invoices = ElectronicInvoice::query()
            ->with('customer:id,business_name,full_name,first_name,last_name,document_number,ruc', 'currency:id,code,symbol')
            ->orderByDesc('id');

        return DataTables::of($invoices)
            ->addIndexColumn()
            ->addColumn('type_label', fn (ElectronicInvoice $invoice) => $this->documentTypeLabel($invoice->document_type))
            ->addColumn('customer_name', fn (ElectronicInvoice $invoice) => $invoice->client_name ?: $this->customerName($invoice->customer))
            ->addColumn('customer_document', fn (ElectronicInvoice $invoice) => $invoice->client_document_number ?: '-')
            ->editColumn('total_amount', fn (ElectronicInvoice $invoice) =>
                trim(($invoice->currency?->symbol ?? '') . ' ' . number_format((float) $invoice->total_amount, 3)))
            ->editColumn('sunat_status', fn (ElectronicInvoice $invoice) => $this->sunatBadge($invoice->sunat_status))
            ->editColumn('status', fn (ElectronicInvoice $invoice) => $this->statusBadge($invoice->status))
            ->editColumn('issue_date', fn (ElectronicInvoice $invoice) => $invoice->issue_date?->format('d/m/Y') ?? '-')
            ->addColumn('acciones', function (ElectronicInvoice $invoice) {
                $apiReady = app(ApiPeruBillingService::class)->canSendToApi($invoice->loadMissing('electronicSeries'));

                return view('admin.electronic-invoices.partials.acciones', compact('invoice', 'apiReady'))->render();
            })
            ->rawColumns(['sunat_status', 'status', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        return $this->saveInvoice($request);
    }

    public function show(ElectronicInvoice $electronicInvoice)
    {
        $electronicInvoice->load([
            'company',
            'customer',
            'quote',
            'customerPurchaseOrder',
            'warehouseEntry',
            'currency',
            'serie',
            'items.article',
            'payments',
            'legends',
            'relatedDocuments',
            'files',
            'apiLogs.executor',
            'statusHistories.user',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $electronicInvoice,
        ]);
    }

    public function edit(ElectronicInvoice $electronicInvoice)
    {
        return $this->show($electronicInvoice);
    }

    public function update(Request $request, ElectronicInvoice $electronicInvoice)
    {
        return $this->saveInvoice($request, $electronicInvoice);
    }

    public function destroy(ElectronicInvoice $electronicInvoice, WarehouseKardexService $kardexService)
    {
        try {
            DB::transaction(function () use ($electronicInvoice, $kardexService) {
                $previousStatus = $electronicInvoice->status;
                $kardexService->reverseElectronicInvoiceExit(
                    $electronicInvoice,
                    'Anulación de salida por comprobante ' . $electronicInvoice->full_number
                );
                $electronicInvoice->update([
                    'status' => 'cancelled',
                    'updated_by' => Auth::id(),
                ]);

                $electronicInvoice->statusHistories()->create([
                    'previous_status' => $previousStatus,
                    'new_status' => 'cancelled',
                    'description' => 'Comprobante cancelado internamente y salida de almacén revertida. No representa baja SUNAT.',
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);
                $electronicInvoice->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Comprobante cancelado correctamente.',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error deleting electronic invoice: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo cancelar el comprobante.',
            ], 500);
        }
    }

    public function pdf(ElectronicInvoice $electronicInvoice)
    {
        $invoice = $electronicInvoice->fresh([
            'company',
            'customer',
            'currency',
            'items',
            'payments',
            'legends',
            'relatedDocuments',
        ]);

        if (! $invoice->pdf_path || ! Storage::disk('public')->exists($invoice->pdf_path)) {
            $this->generateLocalPdf($invoice);
        }

        return response()->file(Storage::disk('public')->path($invoice->fresh()->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($invoice->fresh()->pdf_name ?: $invoice->full_number . '.pdf') . '"',
        ]);
    }

    public function previewPayload(ElectronicInvoice $electronicInvoice)
    {
        $payload = $this->buildPayload($electronicInvoice);
        $electronicInvoice->update(['api_payload' => $payload]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payload preliminar generado. Aun no se envia a APIs Peru.',
            'data' => $payload,
        ]);
    }

    public function sendToApi(ElectronicInvoice $electronicInvoice, ApiPeruBillingService $billingService)
    {
        $result = $billingService->send($electronicInvoice->loadMissing('electronicSeries'));
        ElectronicInvoiceApiLog::create([
            'electronic_invoice_id' => $electronicInvoice->id,
            'provider' => 'apisperu',
            'operation' => 'send',
            'method' => 'POST',
            'success' => false,
            'message' => $result['message'],
            'executed_by' => Auth::id(),
            'executed_at' => now(),
        ]);

        $electronicInvoice->update([
            'sunat_status' => $result['status'],
            'api_message' => $result['message'],
            'updated_by' => Auth::id(),
        ]);

        return response()->json($result);
    }

    public function buildPayload(ElectronicInvoice $invoice): array
    {
        return app(ApiPeruBillingService::class)->buildPayload($invoice);
    }

    private function saveInvoice(Request $request, ?ElectronicInvoice $invoice = null)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_branch_id' => ['nullable', 'exists:customer_branches,id'],
            'quote_id' => ['nullable', 'exists:quotes,id'],
            'customer_purchase_order_id' => ['nullable', 'exists:customer_purchase_orders,id'],
            'warehouse_entry_id' => ['nullable', 'exists:warehouse_entries,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'serie_id' => ['nullable', 'exists:electronic_invoice_series,id'],
            'document_type' => ['required', Rule::in(['01', '03'])],
            'requested_status' => ['required', Rule::in([self::STATUS_DRAFT, self::STATUS_GENERATED])],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_type' => ['required', Rule::in(['Contado', 'Credito'])],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_condition' => ['nullable', 'string', 'max:100'],
            'purchase_order_number' => ['nullable', 'string', 'max:255'],
            'siaf_number' => ['nullable', 'string', 'max:255'],
            'process_number' => ['nullable', 'string', 'max:255'],
            'contract_number' => ['nullable', 'string', 'max:255'],
            'delivery_note' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.article_id' => ['nullable', 'exists:articles,id'],
            'items.*.customer_purchase_order_item_id' => ['nullable', 'exists:customer_purchase_order_items,id'],
            'items.*.product_code' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['required', 'string'],
            'items.*.unit_code' => ['required', 'string', 'max:10'],
            'items.*.unit_name' => ['nullable', 'string', 'max:255'],
            'items.*.brand_name' => ['nullable', 'string', 'max:255'],
            'items.*.presentation_name' => ['nullable', 'string', 'max:255'],
            'items.*.lot_number' => ['nullable', 'string', 'max:255'],
            'items.*.expiration_date' => ['nullable', 'date'],
            'items.*.origin' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_affectation_code' => ['required', Rule::in(['10', '20', '30'])],
            'payments' => ['nullable', 'array'],
            'payments.*.quota_number' => ['nullable', 'integer', 'min:1'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0'],
            'payments.*.due_date' => ['nullable', 'date'],
            'legends' => ['nullable', 'array'],
            'legends.*.code' => ['nullable', 'string', 'max:10'],
            'legends.*.value' => ['nullable', 'string'],
        ], [
            'company_id.required' => 'Debe seleccionar una empresa emisora.',
            'customer_id.required' => 'Debe seleccionar un cliente.',
            'currency_id.required' => 'Debe seleccionar una moneda.',
            'serie_id.exists' => 'La serie electrónica seleccionada no existe.',
            'document_type.required' => 'Debe seleccionar el tipo de comprobante.',
            'items.required' => 'Debe ingresar al menos un artículo o servicio.',
            'items.min' => 'Debe ingresar al menos un artículo o servicio.',
            'items.*.description.required' => 'La descripción del artículo o servicio es obligatoria.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
            'items.*.unit_price.required' => 'El precio unitario es obligatorio.',
            'items.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
            'requested_status.required' => 'Debe indicar si desea guardar el borrador o generar el comprobante.',
            'warehouse_id.exists' => 'El almacén de salida seleccionado no existe.',
        ]);

        try {
            return DB::transaction(function () use ($validated, $invoice) {
                $isCreating = $invoice === null;
                $targetStatus = $validated['requested_status'];
                if ($invoice?->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        'status' => 'No se puede modificar un comprobante anulado.',
                    ]);
                }
                if ($invoice?->status === self::STATUS_GENERATED && $targetStatus === self::STATUS_DRAFT) {
                    throw ValidationException::withMessages([
                        'status' => 'Un comprobante generado no puede volver al estado borrador.',
                    ]);
                }
                $configuration = ElectronicInvoiceSetting::query()
                    ->where('company_id', $validated['company_id'])
                    ->where('is_active', true)
                    ->orderByRaw("CASE WHEN environment = 'internal' THEN 0 WHEN environment = 'beta' THEN 1 ELSE 2 END")
                    ->first();
                if (! $configuration) {
                    throw ValidationException::withMessages([
                        'company_id' => 'La empresa seleccionada no tiene configuración electrónica activa.',
                    ]);
                }
                if ($targetStatus === self::STATUS_GENERATED && empty($validated['warehouse_id'])) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => 'El almacén de salida es obligatorio para generar el comprobante interno.',
                    ]);
                }
                $serieQuery = ElectronicInvoiceSeries::query()->lockForUpdate();
                if (! empty($validated['serie_id'])) {
                    $serieQuery->whereKey($validated['serie_id']);
                } else {
                    $serieQuery
                        ->where('company_id', $validated['company_id'])
                        ->where('document_type', $validated['document_type'])
                        ->where('environment', $configuration->environment)
                        ->where('is_default', true)
                        ->where('status', 'ACTIVE');
                }
                $serie = $serieQuery->first();
                if (! $serie) {
                    throw ValidationException::withMessages([
                        'serie_id' => 'No existe una serie activa para esta empresa, tipo de documento y ambiente.',
                    ]);
                }

                if ($serie->document_type !== $validated['document_type']
                    || (int) $serie->company_id !== (int) $validated['company_id']
                    || $serie->environment !== $configuration->environment
                    || $serie->status !== 'ACTIVE') {
                    throw ValidationException::withMessages([
                        'serie_id' => 'La serie seleccionada no corresponde a la empresa, tipo de documento o no está activa.',
                    ]);
                }

                $company = Company::query()->findOrFail($validated['company_id']);
                $customer = Customer::query()->findOrFail($validated['customer_id']);
                $customerBranch = ! empty($validated['customer_branch_id'])
                    ? CustomerBranch::query()
                        ->where('customer_id', $customer->id)
                        ->findOrFail($validated['customer_branch_id'])
                    : null;
                $currency = Currency::query()->findOrFail($validated['currency_id']);
                $preparedItems = $this->prepareItems($validated['items']);
                $totals = $this->calculateTotals($preparedItems);

                if ($totals['total_amount'] <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'El total del comprobante debe ser mayor a 0.',
                    ]);
                }

                $payments = $this->preparePayments($validated['payment_type'], $validated['payments'] ?? [], $totals['total_amount'], $validated['due_date'] ?? null);
                $this->validatePayments($validated['payment_type'], $payments, $totals['total_amount']);

                $needsFinalNumber = $targetStatus === self::STATUS_GENERATED
                    && ($isCreating || $invoice?->status === self::STATUS_DRAFT);
                $correlativo = $needsFinalNumber
                    ? str_pad((string) $serie->next_number, 8, '0', STR_PAD_LEFT)
                    : ($invoice?->correlativo ?: 'BORRADOR-' . Str::upper(Str::random(8)));
                $fullNumber = $serie->serie . '-' . $correlativo;
                $previousStatus = $invoice?->status;

                $invoiceData = array_merge($totals, [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'customer_branch_id' => $customerBranch?->id,
                    'quote_id' => $validated['quote_id'] ?? null,
                    'customer_purchase_order_id' => $validated['customer_purchase_order_id'] ?? null,
                    'warehouse_entry_id' => $validated['warehouse_entry_id'] ?? null,
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
                    'currency_id' => $currency->id,
                    'serie_id' => $serie->id,
                    'document_type' => $validated['document_type'],
                    'serie' => $serie->serie,
                    'correlativo' => $correlativo,
                    'full_number' => $fullNumber,
                    'issue_date' => $validated['issue_date'],
                    'issue_time' => now()->format('H:i:s'),
                    'due_date' => $validated['due_date'] ?? null,
                    'operation_type' => '0101',
                    'currency_code' => $currency->code ?? 'PEN',
                    'payment_type' => $validated['payment_type'],
                    'payment_method' => $this->upperOrNull($validated['payment_method'] ?? null),
                    'payment_condition' => $this->upperOrNull($validated['payment_condition'] ?? null),
                    'client_document_type' => $this->clientDocumentCode($customer),
                    'client_document_number' => $customer->ruc ?? $customer->document_number,
                    'client_name' => $this->customerName($customer),
                    'client_address' => $customerBranch?->address ?: $customer->address,
                    'client_email' => $customer->email,
                    'client_phone' => $customer->phone,
                    'company_ruc' => $company->ruc,
                    'company_business_name' => $company->business_name,
                    'company_trade_name' => $company->trade_name,
                    'company_address' => $company->address,
                    'purchase_order_number' => $this->upperOrNull($validated['purchase_order_number'] ?? null),
                    'siaf_number' => $this->upperOrNull($validated['siaf_number'] ?? null),
                    'process_number' => $this->upperOrNull($validated['process_number'] ?? null),
                    'contract_number' => $this->upperOrNull($validated['contract_number'] ?? null),
                    'delivery_note' => $this->upperOrNull($validated['delivery_note'] ?? null),
                    'observations' => $this->upperOrNull($validated['observations'] ?? null),
                    'status' => $targetStatus,
                    'api_provider' => $configuration->provider,
                    'updated_by' => Auth::id(),
                ]);

                if ($invoice) {
                    $wasGenerated = $invoice->status === self::STATUS_GENERATED;
                    if ($wasGenerated) {
                        app(WarehouseKardexService::class)->reverseElectronicInvoiceExit(
                            $invoice,
                            'Reversa temporal por actualización del comprobante ' . $invoice->full_number
                        );
                    }
                    $invoice->update($invoiceData);
                    $invoice->items()->delete();
                    $invoice->payments()->delete();
                    $invoice->legends()->delete();
                    $invoice->relatedDocuments()->delete();
                } else {
                    $invoiceData['created_by'] = Auth::id();
                    $invoice = ElectronicInvoice::create($invoiceData);
                    if ($needsFinalNumber) {
                        $serie->update([
                        'current_number' => $serie->next_number,
                        'next_number' => $serie->next_number + 1,
                        'updated_by' => Auth::id(),
                        ]);
                    }
                }

                foreach ($preparedItems as $index => $item) {
                    $invoice->items()->create(array_merge($item, [
                        'item_number' => $index + 1,
                    ]));
                }

                foreach ($payments as $payment) {
                    $invoice->payments()->create($payment);
                }

                $invoice->legends()->create([
                    'code' => '1000',
                    'description' => 'Monto en letras',
                    'value' => $invoice->total_text,
                ]);

                collect($validated['legends'] ?? [])
                    ->filter(fn ($legend) => ! empty($legend['value']))
                    ->each(fn ($legend) => $invoice->legends()->create([
                        'code' => $legend['code'] ?? '9999',
                        'description' => 'Leyenda adicional',
                        'value' => $this->upperOrNull($legend['value']),
                    ]));

                if (! empty($validated['purchase_order_number'])) {
                    $invoice->relatedDocuments()->create([
                        'relation_type' => 'purchase_order',
                        'full_number' => $this->upperOrNull($validated['purchase_order_number']),
                        'description' => 'Orden de compra de cliente',
                    ]);
                }

                $billingService = app(ApiPeruBillingService::class);
                $invoice->update([
                    'sunat_status' => $billingService->externalStatus($invoice->fresh('electronicSeries')),
                    'api_message' => $billingService->canSendToApi($invoice->fresh('electronicSeries'))
                        ? 'Pendiente de envío a SUNAT.'
                        : 'API de facturación aún no configurada.',
                ]);

                $pdfData = null;
                if ($targetStatus === self::STATUS_GENERATED) {
                    app(WarehouseKardexService::class)->registerExitFromElectronicInvoice(
                        $invoice->fresh(['customer', 'warehouseEntry', 'items.article.category'])
                    );
                }

                $invoice->update(['api_payload' => $this->buildPayload($invoice->fresh(['items', 'payments', 'legends', 'relatedDocuments']))]);
                if ($targetStatus === self::STATUS_GENERATED) {
                    $pdfData = $this->generateLocalPdf($invoice->fresh(['company', 'customer', 'currency', 'items', 'payments', 'legends', 'relatedDocuments']));
                }

                $invoice->statusHistories()->create([
                    'previous_status' => $previousStatus,
                    'new_status' => $invoice->status,
                    'description' => $isCreating
                        ? ($targetStatus === self::STATUS_DRAFT
                            ? 'Comprobante guardado como borrador. No mueve stock.'
                            : 'Comprobante generado internamente. Pendiente de envío SUNAT.')
                        : ($targetStatus === self::STATUS_DRAFT
                            ? 'Borrador actualizado. No mueve stock.'
                            : 'Comprobante generado internamente y stock actualizado.'),
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => $isCreating
                        ? ($targetStatus === self::STATUS_DRAFT
                            ? 'Borrador guardado correctamente.'
                            : 'Comprobante generado internamente correctamente.')
                        : ($targetStatus === self::STATUS_DRAFT
                            ? 'Borrador actualizado correctamente.'
                            : 'Comprobante interno actualizado correctamente.'),
                    'data' => $invoice->fresh(['items', 'payments', 'legends']),
                    'pdf_url' => route('admin.electronic-invoices.pdf', $invoice),
                    'pdf_path' => $pdfData['path'] ?? null,
                ], $isCreating ? 201 : 200);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error saving electronic invoice: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar el comprobante electronico.',
            ], 500);
        }
    }

    private function prepareItems(array $items): array
    {
        return collect($items)->map(function (array $item) {
            $quantity = (string) $item['quantity'];
            $unitPrice = (string) $item['unit_price'];
            $discount = (string) ($item['discount_amount'] ?? 0);
            $lineTotal = bcsub(bcmul($quantity, $unitPrice, 10), $discount, 10);
            $lineTotal = bccomp($lineTotal, '0', 10) < 0 ? '0' : $lineTotal;
            $affectation = $item['tax_affectation_code'];
            $subtotal = $affectation === '10' ? bcdiv($lineTotal, '1.18', 10) : $lineTotal;
            $igv = $affectation === '10' ? bcsub($lineTotal, $subtotal, 10) : '0';
            $unitValue = $affectation === '10' ? bcdiv($unitPrice, '1.18', 10) : $unitPrice;
            $taxCode = match ($affectation) {
                '20' => '9997',
                '30' => '9998',
                default => '1000',
            };

            return [
                'article_id' => $item['article_id'] ?? null,
                'customer_purchase_order_item_id' => $item['customer_purchase_order_item_id'] ?? null,
                'product_code' => $this->upperOrNull($item['product_code'] ?? null),
                'description' => $this->upperOrNull($item['description'] ?? ''),
                'commercial_name' => $this->upperOrNull($item['commercial_name'] ?? null),
                'billing_name' => $this->upperOrNull($item['billing_name'] ?? ($item['description'] ?? '')),
                'unit_code' => $this->upperOrNull($item['unit_code'] ?? 'NIU'),
                'unit_name' => $this->upperOrNull($item['unit_name'] ?? null),
                'brand_name' => $this->upperOrNull($item['brand_name'] ?? null),
                'presentation_name' => $this->upperOrNull($item['presentation_name'] ?? null),
                'lot_number' => $this->upperOrNull($item['lot_number'] ?? null),
                'expiration_date' => $item['expiration_date'] ?? null,
                'origin' => $this->upperOrNull($item['origin'] ?? null),
                'health_registration' => $this->upperOrNull($item['health_registration'] ?? null),
                'quantity' => $quantity,
                'unit_value' => $unitValue,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'subtotal' => $subtotal,
                'igv_base' => $affectation === '10' ? $subtotal : '0',
                'igv_amount' => $igv,
                'igv_percentage' => $affectation === '10' ? 18 : 0,
                'tax_affectation_code' => $affectation,
                'tax_code' => $taxCode,
                'tax_name' => $taxCode === '1000' ? 'IGV' : ($taxCode === '9997' ? 'EXO' : 'INA'),
                'tax_type_code' => $taxCode === '1000' ? 'VAT' : 'FRE',
                'total_taxes' => $igv,
                'line_total' => $lineTotal,
                'status' => 'ACTIVE',
            ];
        })->all();
    }

    private function calculateTotals(array $items): array
    {
        $sum = fn ($values, string $key) => $values->reduce(
            fn (string $carry, array $item) => bcadd($carry, (string) $item[$key], 10),
            '0'
        );
        $collection = collect($items);
        $taxable = $sum($collection->where('tax_affectation_code', '10'), 'subtotal');
        $exonerated = $sum($collection->where('tax_affectation_code', '20'), 'subtotal');
        $unaffected = $sum($collection->where('tax_affectation_code', '30'), 'subtotal');
        $igv = $sum($collection, 'igv_amount');
        $subtotal = $sum($collection, 'subtotal');
        $total = $sum($collection, 'line_total');
        $discount = $sum($collection, 'discount_amount');

        return [
            'taxable_amount' => $taxable,
            'exonerated_amount' => $exonerated,
            'unaffected_amount' => $unaffected,
            'discount_total' => $discount,
            'subtotal' => $subtotal,
            'igv_amount' => $igv,
            'total_taxes' => $igv,
            'total_amount' => $total,
            'total_text' => 'SON ' . number_format((float) $total, 2) . ' SOLES',
        ];
    }

    private function preparePayments(string $paymentType, array $payments, float $total, ?string $dueDate): array
    {
        if ($paymentType === 'Contado') {
            return [[
                'payment_type' => 'Contado',
                'quota_number' => null,
                'amount' => $total,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]];
        }

        return collect($payments)
            ->filter(fn ($payment) => (float) ($payment['amount'] ?? 0) > 0)
            ->values()
            ->map(fn ($payment, $index) => [
                'payment_type' => 'Credito',
                'quota_number' => $payment['quota_number'] ?? ($index + 1),
                'amount' => round((float) $payment['amount'], 2),
                'due_date' => $payment['due_date'] ?? null,
                'status' => 'pending',
            ])
            ->all();
    }

    private function validatePayments(string $paymentType, array $payments, float $total): void
    {
        if ($paymentType !== 'Credito') {
            return;
        }

        if (empty($payments)) {
            throw ValidationException::withMessages([
                'payments' => 'Debe registrar al menos una cuota para pago al credito.',
            ]);
        }

        $sum = round((float) collect($payments)->sum('amount'), 2);

        if (abs($sum - round($total, 2)) > 0.01) {
            throw ValidationException::withMessages([
                'payments' => 'La suma de cuotas debe ser igual al total del comprobante.',
            ]);
        }
    }

    private function generateLocalPdf(ElectronicInvoice $invoice): array
    {
        $fileName = 'comprobante_electronico_' . $this->sanitizeFileName($invoice->full_number) . '.pdf';
        $storedPath = 'electronic_invoices/pdfs/' . $fileName;

        $pdf = Pdf::loadView('admin.electronic-invoices.pdf', [
            'invoice' => $invoice,
            'logoUrl' => $this->logoUrl(),
        ])->setPaper('a4', 'portrait')->setOption(['isRemoteEnabled' => true]);

        Storage::disk('public')->put($storedPath, $pdf->output());

        $invoice->files()
            ->where('file_type', 'pdf')
            ->where('source', 'local')
            ->delete();

        $file = $invoice->files()->create([
            'file_type' => 'pdf',
            'file_name' => $fileName,
            'file_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('public')->size($storedPath) ?: 0,
            'source' => 'local',
            'is_generated' => true,
            'created_by' => Auth::id(),
        ]);

        $invoice->update([
            'pdf_name' => $fileName,
            'pdf_path' => $storedPath,
        ]);

        return ['path' => $storedPath, 'file' => $file];
    }

    private function statusBadge(?string $status): string
    {
        $statuses = [
            'draft' => ['Borrador', 'badge-secondary', 'fas fa-pencil-alt'],
            'generated' => ['Generado', 'badge-primary', 'fas fa-file-invoice'],
            'sent' => ['Enviado', 'badge-info', 'fas fa-paper-plane'],
            'accepted' => ['Aceptado SUNAT', 'badge-success', 'fas fa-check-circle'],
            'observed' => ['Observado', 'badge-warning text-dark', 'fas fa-exclamation-triangle'],
            'rejected' => ['Rechazado', 'badge-danger', 'fas fa-times-circle'],
            'voided' => ['Anulado', 'badge-dark', 'fas fa-ban'],
            'cancelled' => ['Cancelado', 'badge-danger', 'fas fa-trash'],
            'error' => ['Error', 'badge-danger', 'fas fa-bug'],
        ];
        [$label, $class, $icon] = $statuses[$status] ?? [strtoupper((string) $status), 'badge-light text-dark border', 'fas fa-info-circle'];

        return '<span class="badge ' . $class . ' rounded-pill px-3 py-2"><i class="' . $icon . ' mr-1"></i>' . e($label) . '</span>';
    }

    private function sunatBadge(?string $status): string
    {
        $statuses = [
            'not_configured' => ['API no configurada', 'badge-secondary', 'fas fa-cog'],
            'pending_send' => ['Pendiente de envío SUNAT', 'badge-warning text-dark', 'fas fa-clock'],
            'sent' => ['Enviado', 'badge-info', 'fas fa-paper-plane'],
            'accepted' => ['Aceptado SUNAT', 'badge-success', 'fas fa-check-circle'],
            'rejected' => ['Rechazado SUNAT', 'badge-danger', 'fas fa-times-circle'],
            'error' => ['Error API', 'badge-danger', 'fas fa-exclamation-triangle'],
        ];
        [$label, $class, $icon] = $statuses[$status] ?? ['No enviado', 'badge-light text-dark border', 'fas fa-info-circle'];

        return '<span class="badge ' . $class . ' rounded-pill px-3 py-2"><i class="' . $icon . ' mr-1"></i>' . e($label) . '</span>';
    }

    private function documentTypeLabel(string $type): string
    {
        return [
            '01' => 'Factura',
            '03' => 'Boleta',
            '07' => 'Nota de Credito',
            '08' => 'Nota de Debito',
        ][$type] ?? $type;
    }

    private function clientDocumentCode(Customer $customer): ?string
    {
        return match (mb_strtoupper((string) $customer->document_type)) {
            'RUC' => '6',
            'DNI' => '1',
            default => null,
        };
    }

    private function customerName(?Customer $customer): string
    {
        if (! $customer) {
            return '-';
        }

        return $customer->business_name
            ?? $customer->full_name
            ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
            ?: '-';
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value, 'UTF-8');
    }

    private function sanitizeFileName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);
    }

    private function logoUrl(): ?string
    {
        $logoPath = public_path('vendor/adminlte/dist/img/logo_img.png');

        return file_exists($logoPath) ? url('vendor/adminlte/dist/img/logo_img.png') : null;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderItem;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceApiLog;
use App\Models\ElectronicInvoiceSeries;
use App\Models\ElectronicInvoiceSetting;
use App\Models\Quote;
use App\Models\SunatCatalogItem;
use App\Models\WarehouseEntry;
use App\Models\Warehouse;
use App\Services\WarehouseKardexService;
use App\Services\InvoiceCollectionService;
use App\Services\InvoiceFromCustomerOrderService;
use App\Services\ElectronicInvoiceFormDataService;
use App\Services\SunatUnitPolicy;
use App\Services\ArticleSalesTaxPolicy;
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

    private const IMMUTABLE_MESSAGE = 'El comprobante generado ya no puede editarse. Si necesita corregirlo, debe cancelarlo y emitir un nuevo comprobante.';

    public function __construct()
    {
        $this->middleware('can:admin.electronic-invoices.index')->only(['index', 'list']);
        $this->middleware('can:admin.electronic-invoices.create')->only(['store']);
        $this->middleware('can:admin.electronic-invoices.show')->only(['show']);
        $this->middleware('can:admin.electronic-invoices.update')->only(['edit', 'update']);
        $this->middleware('can:admin.electronic-invoices.destroy')->only(['destroy']);
        $this->middleware('can:admin.electronic-invoices.pdf')->only(['pdf']);
        $this->middleware('can:admin.electronic-invoices.payload')->only(['previewPayload']);
        $this->middleware('can:admin.electronic-invoices.send')->only(['sendToApi']);
        $this->middleware('can:admin.electronic-invoices.collect')->only(['collectionAccounts']);
        $this->middleware('can:admin.invoice-collections.store')->only(['collect']);
    }

    public function index(Request $request, ElectronicInvoiceFormDataService $formDataService)
    {
        $formData = $formDataService->get();
        $warehouseEntries = WarehouseEntry::query()
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'entry_number', 'supplier_id', 'grand_total']);
        $bankAccounts = CompanyBankAccount::query()
            ->with(['company:id,business_name', 'bank:id,description,short_name', 'currency:id,code,symbol'])
            ->where('status', 'ACTIVE')
            ->orderBy('company_id')->orderBy('id')->get();
        $collectionAlerts = [
            'pending' => ElectronicInvoice::query()->where('status', self::STATUS_GENERATED)->where('is_voided', false)->where('payment_status', 'pending')->where(fn ($query) => $query->whereNull('due_date')->orWhereDate('due_date', '>=', today()))->count(),
            'due_soon' => ElectronicInvoice::query()->where('status', self::STATUS_GENERATED)->where('is_voided', false)->whereIn('payment_status', ['pending', 'partial'])->whereBetween('due_date', [today(), today()->addDays(5)])->count(),
            'partial' => ElectronicInvoice::query()->where('status', self::STATUS_GENERATED)->where('is_voided', false)->where('payment_status', 'partial')->count(),
            'overdue' => ElectronicInvoice::query()->where('status', self::STATUS_GENERATED)->where('is_voided', false)->whereIn('payment_status', ['pending', 'partial'])->whereDate('due_date', '<', today())->count(),
        ];
        $initialCustomerPurchaseOrderId = $request->integer('customer_purchase_order_id') ?: null;
        $initialCollectionInvoiceId = $request->integer('collect_invoice_id') ?: null;
        $invoiceOrderFilterId = $request->integer('invoice_order_id') ?: null;

        return view('admin.electronic-invoices.index', array_merge($formData, compact(
            'warehouseEntries',
            'bankAccounts',
            'collectionAlerts',
            'initialCustomerPurchaseOrderId',
            'initialCollectionInvoiceId',
            'invoiceOrderFilterId'
        )));
    }

    public function customerPurchaseOrderData(
        CustomerPurchaseOrder $customerPurchaseOrder,
        InvoiceFromCustomerOrderService $invoiceService
    )
    {
        abort_unless(
            request()->user()?->can('admin.electronic-invoices.index')
                || request()->user()?->can('admin.customer-purchase-orders.invoice'),
            403
        );

        if (! in_array($customerPurchaseOrder->status, ['partial_entered', 'entered', 'attended', 'delivered'], true)) {
            return response()->json(['message' => 'La orden seleccionada todavía no está disponible para facturación.'], 422);
        }

        return response()->json(['data' => $invoiceService->prepare($customerPurchaseOrder)]);
    }

    public function list(Request $request)
    {
        $invoices = ElectronicInvoice::query()
            ->with('customer:id,business_name,full_name,first_name,last_name,document_number,ruc', 'currency:id,code,symbol')
            ->when($request->integer('customer_purchase_order_id'), fn ($query, $orderId) =>
                $query->where('customer_purchase_order_id', $orderId))
            ->orderByDesc('id');

        return DataTables::of($invoices)
            ->addIndexColumn()
            ->addColumn('type_label', fn (ElectronicInvoice $invoice) => $this->documentTypeLabel($invoice->document_type))
            ->addColumn('customer_name', fn (ElectronicInvoice $invoice) => $invoice->client_name ?: $this->customerName($invoice->customer))
            ->addColumn('customer_document', fn (ElectronicInvoice $invoice) => $invoice->client_document_number ?: '-')
            ->addColumn('payment_status_label', fn (ElectronicInvoice $invoice) => $this->paymentStatusBadge($invoice))
            ->addColumn('pending_amount_label', fn (ElectronicInvoice $invoice) => trim(($invoice->currency?->symbol ?? '').' '.number_format((float) $invoice->pending_amount, 2)))
            ->editColumn('total_amount', fn (ElectronicInvoice $invoice) =>
                trim(($invoice->currency?->symbol ?? '') . ' ' . number_format((float) $invoice->total_amount, 3)))
            ->editColumn('sunat_status', fn (ElectronicInvoice $invoice) => $this->sunatBadge($invoice->sunat_status))
            ->editColumn('status', fn (ElectronicInvoice $invoice) => $this->statusBadge($invoice->status))
            ->editColumn('issue_date', fn (ElectronicInvoice $invoice) => $invoice->issue_date?->format('d/m/Y') ?? '-')
            ->addColumn('acciones', function (ElectronicInvoice $invoice) {
                $apiReady = app(ApiPeruBillingService::class)->canSendToApi($invoice->loadMissing('electronicSeries'));

                return view('admin.electronic-invoices.partials.acciones', compact('invoice', 'apiReady'))->render();
            })
            ->rawColumns(['sunat_status', 'status', 'payment_status_label', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        return $this->saveInvoice($request);
    }

    public function show(
        ElectronicInvoice $electronicInvoice,
        InvoiceFromCustomerOrderService $invoiceService
    )
    {
        $electronicInvoice->load([
            'company',
            'customer',
            'quote',
            'customerPurchaseOrder',
            'warehouseEntry',
            'warehouse',
            'currency',
            'serie',
            'items.article',
            'items.dispatchAllocations.dispatchItem.dispatch',
            'payments',
            'collections.account.bank',
            'collections.currency',
            'collections.bankMovement',
            'collections.creator',
            'legends',
            'relatedDocuments',
            'files',
            'apiLogs.executor',
            'statusHistories.user',
        ]);
        $electronicInvoice->collections->each(function ($collection) {
            $collection->setAttribute(
                'proof_url',
                $collection->proof_file_path ? Storage::disk('public')->url($collection->proof_file_path) : null
            );
        });
        $electronicInvoice->setAttribute(
            'dispatch_context',
            $invoiceService->dispatchTraceability($electronicInvoice)
        );

        return response()->json([
            'status' => 'success',
            'data' => $electronicInvoice,
        ]);
    }

    public function collectionAccounts(ElectronicInvoice $electronicInvoice)
    {
        return response()->json(['data' => CompanyBankAccount::query()
            ->with(['bank:id,description,short_name', 'currency:id,code,symbol'])
            ->where('company_id', $electronicInvoice->company_id)
            ->where('status', 'ACTIVE')
            ->orderBy('id')->get()]);
    }

    public function collect(Request $request, ElectronicInvoice $electronicInvoice, InvoiceCollectionService $service)
    {
        $validated = $request->validate([
            'company_bank_account_id' => ['required', 'integer', 'exists:company_bank_accounts,id'],
            'collection_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'operation_number' => ['nullable', 'string', 'max:100'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'observation' => ['nullable', 'string', 'max:1500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ], [
            'company_bank_account_id.required' => 'Seleccione la cuenta bancaria donde ingresó el cobro.',
            'amount.gt' => 'El monto cobrado debe ser mayor a cero.',
            'proof.mimes' => 'La constancia debe ser PDF, JPG, JPEG, PNG o WEBP.',
            'proof.max' => 'La constancia no debe superar los 10 MB.',
        ]);

        $collection = $service->register($electronicInvoice, $validated, $request->file('proof'), Auth::id());

        return response()->json([
            'status' => 'success',
            'message' => 'Cobro confirmado e ingreso bancario registrado correctamente.',
            'data' => $collection,
            'invoice' => $electronicInvoice->fresh(),
        ], 201);
    }

    public function edit(ElectronicInvoice $electronicInvoice)
    {
        $this->ensureInvoiceEditable($electronicInvoice);

        return $this->show($electronicInvoice, app(InvoiceFromCustomerOrderService::class));
    }

    public function update(Request $request, ElectronicInvoice $electronicInvoice)
    {
        $this->ensureInvoiceEditable($electronicInvoice);

        return $this->saveInvoice($request, $electronicInvoice);
    }

    public function destroy(
        Request $request,
        ElectronicInvoice $electronicInvoice,
        WarehouseKardexService $kardexService,
        InvoiceFromCustomerOrderService $invoiceService
    )
    {
        if (in_array($electronicInvoice->status, ['cancelled', 'voided'], true) || $electronicInvoice->is_voided) {
            return response()->json([
                'message' => 'El comprobante ya se encuentra cancelado o anulado y no puede modificarse.',
            ], 422);
        }
        if ($electronicInvoice->is_sent_to_sunat || in_array($electronicInvoice->status, ['sent', 'accepted'], true)) {
            return response()->json([
                'message' => 'El comprobante enviado a SUNAT requiere el procedimiento fiscal de anulación correspondiente.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Debe ingresar el motivo de cancelación del comprobante.',
        ]);

        if ($electronicInvoice->collections()->exists()) {
            return response()->json([
                'message' => 'No se puede anular una factura con cobros registrados. El historial bancario debe conservarse.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($electronicInvoice, $kardexService, $invoiceService, $validated) {
                $previousStatus = $electronicInvoice->status;
                $dispatchBacked = $invoiceService->isDispatchBackedInvoice($electronicInvoice);
                if ($dispatchBacked) {
                    $invoiceService->releaseDispatchAllocations($electronicInvoice);
                } elseif ($electronicInvoice->stock_moved_at) {
                    $kardexService->reverseElectronicInvoiceExit(
                        $electronicInvoice,
                        'Anulación de salida por comprobante ' . $electronicInvoice->full_number
                    );
                }
                $electronicInvoice->update([
                    'status' => 'cancelled',
                    'voided_at' => now(),
                    'voided_reason' => $validated['reason'],
                    'updated_by' => Auth::id(),
                ]);

                $electronicInvoice->statusHistories()->create([
                    'previous_status' => $previousStatus,
                    'new_status' => 'cancelled',
                    'description' => $dispatchBacked
                        ? 'Comprobante cancelado internamente. Se liberó su asignación comercial sin revertir el despacho físico. Motivo: '.$validated['reason']
                        : 'Comprobante cancelado internamente. Motivo: '.$validated['reason'],
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);
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
            'items.*.warehouse_dispatch_item_id' => ['nullable', 'integer', 'exists:warehouse_dispatch_items,id'],
            'items.*.product_code' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['required', 'string'],
            'items.*.unit_code' => ['nullable', 'string', 'max:10'],
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
                $invoiceService = app(InvoiceFromCustomerOrderService::class);
                if ($invoice && (float) $invoice->paid_amount > 0) {
                    throw ValidationException::withMessages([
                        'invoice' => 'No se puede modificar una factura que ya tiene cobros registrados.',
                    ]);
                }
                $configuration = ElectronicInvoiceSetting::query()
                    ->where('company_id', $validated['company_id'])
                    ->where('is_active', true)
                    ->where('environment', 'internal')
                    ->first();
                if (! $configuration) {
                    throw ValidationException::withMessages([
                        'company_id' => 'Configuración local requerida. La empresa seleccionada no tiene una configuración de facturación local activa.',
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
                        'serie_id' => 'Serie local requerida. Configure una serie interna activa para emitir este comprobante.',
                    ]);
                }

                if ($serie->document_type !== $validated['document_type']
                    || (int) $serie->company_id !== (int) $validated['company_id']
                    || $serie->environment !== $configuration->environment
                    || $serie->status !== 'ACTIVE') {
                    throw ValidationException::withMessages([
                        'serie_id' => 'La serie local seleccionada no corresponde a la empresa, tipo de documento o no está activa.',
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
                $validated['items'] = $this->applySunatUnitCodes($validated['items']);
                $preparedItems = $this->prepareItems($validated['items']);
                $totals = $this->calculateTotals($preparedItems);
                $customerOrder = ! empty($validated['customer_purchase_order_id'])
                    ? CustomerPurchaseOrder::query()->findOrFail($validated['customer_purchase_order_id'])
                    : null;
                $useDispatchBacking = $targetStatus === self::STATUS_GENERATED
                    && $customerOrder
                    && $invoiceService->shouldUseDispatchBacking($customerOrder, $invoice);

                if ($targetStatus === self::STATUS_GENERATED
                    && ! $useDispatchBacking
                    && empty($validated['warehouse_id'])) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => 'El almacén de salida es obligatorio para generar el comprobante interno.',
                    ]);
                }

                if ($customerOrder) {
                    if ((int) $customerOrder->company_id !== (int) $company->id
                        || (int) $customerOrder->customer_id !== (int) $customer->id
                        || (int) $customerOrder->currency_id !== (int) $currency->id) {
                        throw ValidationException::withMessages([
                            'customer_purchase_order_id' => 'La empresa, cliente y moneda deben coincidir con la orden de compra seleccionada.',
                        ]);
                    }
                    if ($targetStatus === self::STATUS_GENERATED) {
                        $invoiceService->validateGeneratedInvoice(
                            $customerOrder,
                            $validated['items'],
                            $invoice,
                            (float) $totals['total_amount'],
                            $useDispatchBacking
                        );
                    }
                }

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
                $paidAmount = (float) ($invoice?->paid_amount ?? 0);
                $pendingAmount = $targetStatus === self::STATUS_GENERATED
                    ? max(0, round($totals['total_amount'] - $paidAmount, 10))
                    : 0;

                $invoiceData = array_merge($totals, [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'customer_branch_id' => $customerBranch?->id,
                    'quote_id' => $validated['quote_id'] ?? null,
                    'customer_purchase_order_id' => $validated['customer_purchase_order_id'] ?? null,
                    'warehouse_entry_id' => $validated['warehouse_entry_id'] ?? null,
                    'warehouse_id' => $useDispatchBacking ? null : ($validated['warehouse_id'] ?? null),
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
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'payment_status' => $pendingAmount <= 0.00001 && $paidAmount > 0 ? 'paid' : 'pending',
                    'api_provider' => $configuration->provider,
                    'updated_by' => Auth::id(),
                ]);

                if ($invoice) {
                    $invoice->update($invoiceData);
                    $invoice->items()->delete();
                    $invoice->payments()->delete();
                    $invoice->legends()->delete();
                    $invoice->relatedDocuments()->delete();
                } else {
                    $invoiceData['created_by'] = Auth::id();
                    $invoice = ElectronicInvoice::create($invoiceData);
                }
                if ($needsFinalNumber) {
                    $serie->update([
                        'current_number' => $serie->next_number,
                        'next_number' => $serie->next_number + 1,
                        'updated_by' => Auth::id(),
                    ]);
                }

                foreach (array_values($preparedItems) as $index => $item) {
                    $invoice->items()->create(array_merge($item, [
                        'item_number' => $index + 1,
                    ]));
                }

                if ($targetStatus === self::STATUS_GENERATED && $useDispatchBacking) {
                    $invoiceService->allocateDispatchesForGeneratedInvoice(
                        $customerOrder,
                        $invoice,
                        $validated['items']
                    );
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
                if ($targetStatus === self::STATUS_GENERATED && ! $useDispatchBacking) {
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
                            : ($useDispatchBacking
                                ? 'Comprobante generado con respaldo de despacho confirmado. No genera una salida física adicional.'
                                : 'Comprobante generado internamente. Pendiente de envío SUNAT.'))
                        : ($targetStatus === self::STATUS_DRAFT
                            ? 'Borrador actualizado. No mueve stock.'
                            : ($useDispatchBacking
                                ? 'Comprobante actualizado con respaldo de despacho confirmado. No modifica stock.'
                                : 'Comprobante generado internamente y stock actualizado.')),
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
        $orderItemIds = collect($items)->pluck('customer_purchase_order_item_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $orderItems = CustomerPurchaseOrderItem::query()
            ->with('purchaseOrder:id,affect_igv')
            ->whereKey($orderItemIds)
            ->get()
            ->keyBy('id');
        $salesTaxPolicy = app(ArticleSalesTaxPolicy::class);

        return collect($items)->map(function (array $item) use ($orderItems, $salesTaxPolicy) {
            $affectation = trim((string) ($item['tax_affectation_code'] ?? ''));
            $orderItem = $orderItems->get((int) ($item['customer_purchase_order_item_id'] ?? 0));

            if ($orderItem) {
                $snapshot = trim((string) $orderItem->tax_affectation_code);
                $affectation = in_array($snapshot, ArticleSalesTaxPolicy::ALLOWED, true)
                    ? $snapshot
                    : ($orderItem->purchaseOrder?->affect_igv
                        ? ArticleSalesTaxPolicy::TAXABLE
                        : ArticleSalesTaxPolicy::EXONERATED);
            } else {
                // En una factura directa la afectación se define en ESTA línea
                // del comprobante. Nunca se vuelve a leer del maestro Article.
                $affectation = $salesTaxPolicy->validate($affectation, 'items');
            }

            $quantity = (string) $item['quantity'];
            $unitPrice = (string) $item['unit_price'];
            $discount = (string) ($item['discount_amount'] ?? 0);
            $lineTotal = bcsub(bcmul($quantity, $unitPrice, 10), $discount, 10);
            $lineTotal = bccomp($lineTotal, '0', 10) < 0 ? '0' : $lineTotal;
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
                'unit_code' => $this->upperOrNull($item['unit_code']),
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

    private function applySunatUnitCodes(array $items): array
    {
        $articleIds = collect($items)
            ->pluck('article_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();
        $articles = Article::query()
            ->with('unit.sunatUnit.catalog')
            ->whereKey($articleIds)
            ->get()
            ->keyBy('id');
        $policy = app(SunatUnitPolicy::class);

        return collect($items)->map(function (array $item) use ($articles, $policy) {
            $article = $articles->get((int) ($item['article_id'] ?? 0));
            if (! $article) {
                throw ValidationException::withMessages([
                    'items' => SunatUnitPolicy::BILLING_MESSAGE,
                ]);
            }

            $item['unit_code'] = $policy->codeForArticle($article, 'items');

            return $item;
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

    private function paymentStatusBadge(ElectronicInvoice $invoice): string
    {
        $statuses = [
            'draft' => ['Borrador', 'badge-secondary', 'fas fa-pencil-alt'],
            'pending' => ['Pendiente de cobro', 'badge-warning text-dark', 'fas fa-clock'],
            'partial' => ['Cobro parcial', 'badge-info', 'fas fa-coins'],
            'paid' => ['Cobrada', 'badge-success', 'fas fa-check-circle'],
            'overdue' => ['Vencida', 'badge-danger', 'fas fa-exclamation-circle'],
            'cancelled' => ['Anulada', 'badge-dark', 'fas fa-ban'],
        ];
        [$label, $class, $icon] = $statuses[$invoice->effectivePaymentStatus()] ?? $statuses['pending'];

        return '<span class="badge '.$class.' rounded-pill px-3 py-2"><i class="'.$icon.' mr-1"></i>'.e($label).'</span>';
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

    private function ensureInvoiceEditable(ElectronicInvoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            throw ValidationException::withMessages([
                'status' => self::IMMUTABLE_MESSAGE,
            ]);
        }
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

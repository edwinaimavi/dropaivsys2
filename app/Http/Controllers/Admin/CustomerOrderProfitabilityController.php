<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Document;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDocument;
use App\Services\CustomerOrderProfitabilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class CustomerOrderProfitabilityController extends Controller
{
    public function __construct(private CustomerOrderProfitabilityService $service)
    {
        $this->middleware('can:admin.customer-order-profitability.index')->only(['index', 'list']);
        $this->middleware('can:admin.customer-order-profitability.show')->only([
            'show', 'viewDocument', 'viewExpenseDocument', 'viewLegacyExpenseDocument',
        ]);
        $this->middleware('can:admin.customer-order-profitability.calculate')->only('calculate');
        $this->middleware('can:admin.customer-order-profitability.recalculate')->only('recalculate');
        $this->middleware('can:admin.customer-order-profitability.export')->only('pdf');
        $this->middleware('can:admin.customer-order-profitability.print')->only('print');
    }

    public function index()
    {
        return view('admin.customer-order-profitability.index', [
            'companies' => Company::query()->orderBy('business_name')->get(['id', 'business_name', 'trade_name']),
            'customers' => Customer::query()->orderBy('business_name')->get(['id', 'business_name', 'full_name']),
        ]);
    }

    public function list(Request $request)
    {
        $mode = $request->input('mode', CustomerOrderProfitabilityService::MODE_WITHOUT_IGV);
        $orders = CustomerPurchaseOrder::query()->with(['customer:id,business_name,full_name', 'company:id,business_name,trade_name', 'currency:id,code,symbol'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->company_id))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('search_order'), fn ($q) => $q->where(
                fn ($search) => $search
                    ->where('code', 'like', '%'.$request->search_order.'%')
                    ->orWhere('purchase_order_number', 'like', '%'.$request->search_order.'%')
            ))
            ->latest()->get();
        $rows = $orders->map(function ($order) use ($mode) {
            $data = $this->service->calculate($order, $mode);
            $status = CustomerPurchaseOrder::statusPresentation($order->status);

            return ['id' => $order->id, 'code' => $order->code, 'purchase_order_number' => $order->purchase_order_number, 'customer' => $order->customer?->business_name ?: $order->customer?->full_name, 'company' => $order->company?->trade_name ?: $order->company?->business_name, 'currency' => 'S/', 'sale_total' => $data['saleValue'], 'purchase_total' => $data['purchaseValue'], 'linked_costs_total' => $data['linkedTotal'], 'net_profit' => $data['net'], 'profitability_base' => $data['profitabilityBase'], 'profitability_percentage' => $data['percentage'], 'status_label' => $status['label'], 'status_class' => $status['class'], 'status_icon' => $status['icon']];
        });

        $totals = [
            'sale_total' => round((float) $rows->sum('sale_total'), 2),
            'purchase_total' => round((float) $rows->sum('purchase_total'), 2),
            'cost_total' => round((float) $rows->sum('linked_costs_total'), 2),
            'net_profit_total' => round((float) $rows->sum('net_profit'), 2),
        ];

        return DataTables::of($rows)
            ->addIndexColumn()
            ->with(['totals' => $totals])
            ->make(true);
    }

    public function show(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        return response()->json($this->payload($customerPurchaseOrder, $request->input('mode')));
    }

    public function viewDocument(CustomerPurchaseOrder $customerPurchaseOrder, Document $document)
    {
        abort_unless(
            $document->documentable_type === CustomerPurchaseOrder::class
            && (int) $document->documentable_id === (int) $customerPurchaseOrder->id,
            404
        );

        abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        $fileName = str_replace(["\r", "\n", '"'], '', basename($document->original_name ?: $document->file_path));

        return response()->file(Storage::disk('public')->path($document->file_path), [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    public function viewExpenseDocument(
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseEntryExpenseDocument $expenseDocument
    ) {
        $expenseDocument->loadMissing('expense');
        abort_unless($expenseDocument->expense, 404);
        $this->assertLinkedExpense($customerPurchaseOrder, $expenseDocument->expense);
        abort_unless(
            $expenseDocument->status === 'ACTIVE'
            && filled($expenseDocument->file_path)
            && Storage::disk('public')->exists($expenseDocument->file_path),
            404
        );

        return $this->inlinePublicFile(
            $expenseDocument->file_path,
            $expenseDocument->mime_type,
            $expenseDocument->original_name
        );
    }

    public function viewLegacyExpenseDocument(
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseEntryExpense $expense,
        string $type
    ) {
        abort_unless(in_array($type, [
            WarehouseEntryExpenseDocument::TYPE_INVOICE,
            WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF,
        ], true), 404);
        $this->assertLinkedExpense($customerPurchaseOrder, $expense);

        $path = $type === WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF
            ? $expense->payment_proof_path
            : $expense->official_document_path;
        abort_unless(filled($path) && Storage::disk('public')->exists($path), 404);

        return $this->inlinePublicFile($path);
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate(['customer_purchase_order_id' => 'required|exists:customer_purchase_orders,id', 'mode' => 'required|in:without_igv,with_igv']);

        return response()->json($this->payload(CustomerPurchaseOrder::findOrFail($validated['customer_purchase_order_id']), $validated['mode'], true));
    }

    public function recalculate(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $request->validate(['mode' => 'required|in:without_igv,with_igv']);

        return response()->json($this->payload($customerPurchaseOrder, $request->mode, true));
    }

    public function pdf(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $data = $this->service->calculate(
            $customerPurchaseOrder,
            $request->input('mode', CustomerOrderProfitabilityService::MODE_WITHOUT_IGV)
        );
        $this->appendLinkedExpenseAttachments($customerPurchaseOrder, $data['costs']);

        return Pdf::loadView('admin.customer-order-profitability.pdf', $data)->setPaper('a4', 'landscape')->stream('rentabilidad_'.$customerPurchaseOrder->code.'.pdf');
    }

    public function print(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $data = $this->service->calculate(
            $customerPurchaseOrder,
            $request->input('mode', CustomerOrderProfitabilityService::MODE_WITHOUT_IGV)
        );
        $this->appendLinkedExpenseAttachments($customerPurchaseOrder, $data['costs']);

        return view('admin.customer-order-profitability.pdf', $data + ['printMode' => true]);
    }

    private function payload(CustomerPurchaseOrder $order, ?string $mode, bool $save = false): array
    {
        $data = $this->service->calculate($order, $mode ?: CustomerOrderProfitabilityService::MODE_WITHOUT_IGV);
        $this->appendLinkedExpenseAttachments($order, $data['costs']);
        $data['orderDocuments'] = $order->documents->where('status', 'ACTIVE')->map(fn (Document $document) => [
            'type' => $document->documentType?->description ?: $document->documentType?->code ?: 'Documento',
            'file' => $document->original_name ?: basename((string) $document->file_path),
            'date' => ($document->issue_date ?: $document->created_at)?->format('d/m/Y'),
            'view_url' => route('admin.customer-order-profitability.documents.view', [$order, $document]),
        ])->values();
        $snapshot = $save ? $this->service->saveSnapshot($data) : $order->profitabilityAnalyses()->where('calculation_mode', $data['mode'])->latest()->first();

        return ['html' => view('admin.customer-order-profitability.partials.detail', $data + compact('snapshot'))->render(), 'metrics' => ['net_profit' => $data['net'], 'profitability_base' => $data['profitabilityBase'], 'profitability_percentage' => $data['percentage']], 'warnings' => $data['warnings']];
    }

    private function appendLinkedExpenseAttachments(CustomerPurchaseOrder $order, $costs): void
    {
        collect($costs)->each(function (WarehouseEntryExpense $expense) use ($order) {
            $expense->loadMissing('documents');
            $attachments = $expense->documents
                ->filter(fn (WarehouseEntryExpenseDocument $document) => filled($document->file_path))
                ->sortBy('id')
                ->map(fn (WarehouseEntryExpenseDocument $document) => $this->expenseDocumentMetadata(
                    $expense,
                    WarehouseEntryExpenseDocument::normalizeType($document->document_type),
                    $document->file_path,
                    $document->mime_type,
                    route('admin.customer-order-profitability.expense-documents.view', [$order, $document])
                ));

            foreach ([
                WarehouseEntryExpenseDocument::TYPE_INVOICE => $expense->official_document_path,
                WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF => $expense->payment_proof_path,
            ] as $type => $path) {
                if (blank($path) || $attachments->contains(fn (array $item) => $item['path_key'] === $path)) {
                    continue;
                }
                $attachments->push($this->expenseDocumentMetadata(
                    $expense,
                    $type,
                    $path,
                    null,
                    route('admin.customer-order-profitability.expense-files.view', [$order, $expense, $type])
                ));
            }

            $expense->setAttribute('profitability_attachments', $attachments
                ->map(fn (array $item) => collect($item)->except('path_key')->all())
                ->values()
                ->all());
        });
    }

    private function expenseDocumentMetadata(
        WarehouseEntryExpense $expense,
        string $type,
        string $path,
        ?string $mimeType,
        string $viewUrl
    ): array {
        $exists = Storage::disk('public')->exists($path);
        $mimeType = $mimeType ?: $this->publicFileMimeType($path, $exists);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return [
            'path_key' => $path,
            'label' => $this->expenseAttachmentLabel($expense, $type),
            'status' => $exists ? 'available' : 'missing',
            'view_url' => $exists ? $viewUrl : null,
            'is_image' => str_starts_with((string) $mimeType, 'image/')
                || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
        ];
    }

    private function expenseAttachmentLabel(WarehouseEntryExpense $expense, string $type): string
    {
        if ($type === WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF) {
            return 'Ver pago';
        }

        return match (WarehouseEntryExpense::normalizeDocumentType($expense->document_type)) {
            'RECIBO_INTERNO' => 'Ver recibo interno',
            'SIN_COMPROBANTE' => 'Ver sustento',
            default => 'Ver comprobante',
        };
    }

    private function assertLinkedExpense(
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseEntryExpense $expense
    ): void {
        $costs = $this->service->calculate($customerPurchaseOrder)['costs'];
        abort_unless(collect($costs)->contains(fn ($cost) => (int) $cost->id === (int) $expense->id), 404);
    }

    private function publicFileMimeType(string $path, bool $exists = true): ?string
    {
        if (! $exists) {
            return null;
        }

        try {
            return Storage::disk('public')->mimeType($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function inlinePublicFile(string $path, ?string $mimeType = null, ?string $originalName = null)
    {
        $fileName = str_replace(["\r", "\n", '"'], '', basename($originalName ?: $path));

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => $mimeType ?: $this->publicFileMimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }
}

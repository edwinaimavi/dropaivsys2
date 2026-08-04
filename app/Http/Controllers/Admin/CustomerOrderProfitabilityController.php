<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Document;
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
        $this->middleware('can:admin.customer-order-profitability.show')->only(['show', 'viewDocument']);
        $this->middleware('can:admin.customer-order-profitability.calculate')->only('calculate');
        $this->middleware('can:admin.customer-order-profitability.recalculate')->only('recalculate');
        $this->middleware('can:admin.customer-order-profitability.export')->only('pdf');
        $this->middleware('can:admin.customer-order-profitability.print')->only('print');
    }

    public function index()
    {
        return view('admin.customer-order-profitability.index', [
            'companies' => Company::query()->orderBy('business_name')->get(['id','business_name','trade_name']),
            'customers' => Customer::query()->orderBy('business_name')->get(['id','business_name','full_name']),
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
                    ->where('code', 'like', '%' . $request->search_order . '%')
                    ->orWhere('purchase_order_number', 'like', '%' . $request->search_order . '%')
            ))
            ->latest()->get();
        $rows = $orders->map(function ($order) use ($mode) {
            $data = $this->service->calculate($order, $mode);
            $status = CustomerPurchaseOrder::statusPresentation($order->status);
            return ['id'=>$order->id,'code'=>$order->code,'purchase_order_number'=>$order->purchase_order_number,'customer'=>$order->customer?->business_name ?: $order->customer?->full_name,'company'=>$order->company?->trade_name ?: $order->company?->business_name,'currency'=>$order->currency?->symbol ?: $order->currency?->code,'sale_total'=>$data['saleTotal'],'purchase_total'=>$data['purchaseTotal'],'linked_costs_total'=>$data['linkedTotal'],'net_profit'=>$data['net'],'profitability_percentage'=>$data['percentage'],'status_label'=>$status['label'],'status_class'=>$status['class'],'status_icon'=>$status['icon']];
        });
        return DataTables::of($rows)->addIndexColumn()->make(true);
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

    public function calculate(Request $request)
    {
        $validated = $request->validate(['customer_purchase_order_id'=>'required|exists:customer_purchase_orders,id','mode'=>'required|in:without_igv,with_igv']);
        return response()->json($this->payload(CustomerPurchaseOrder::findOrFail($validated['customer_purchase_order_id']), $validated['mode'], true));
    }

    public function recalculate(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $request->validate(['mode'=>'required|in:without_igv,with_igv']);
        return response()->json($this->payload($customerPurchaseOrder, $request->mode, true));
    }

    public function pdf(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $data = $this->service->calculate($customerPurchaseOrder, $request->input('mode'));
        return Pdf::loadView('admin.customer-order-profitability.pdf', $data)->setPaper('a4', 'landscape')->stream('rentabilidad_'.$customerPurchaseOrder->code.'.pdf');
    }

    public function print(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        return view('admin.customer-order-profitability.pdf', $this->service->calculate($customerPurchaseOrder, $request->input('mode')) + ['printMode'=>true]);
    }

    private function payload(CustomerPurchaseOrder $order, ?string $mode, bool $save = false): array
    {
        $data = $this->service->calculate($order, $mode ?: CustomerOrderProfitabilityService::MODE_WITHOUT_IGV);
        $data['orderDocuments'] = $order->documents->where('status', 'ACTIVE')->map(fn (Document $document) => [
            'type' => $document->documentType?->description ?: $document->documentType?->code ?: 'Documento',
            'file' => $document->original_name ?: basename((string) $document->file_path),
            'date' => ($document->issue_date ?: $document->created_at)?->format('d/m/Y'),
            'view_url' => route('admin.customer-order-profitability.documents.view', [$order, $document]),
        ])->values();
        $snapshot = $save ? $this->service->saveSnapshot($data) : $order->profitabilityAnalyses()->where('calculation_mode', $data['mode'])->latest()->first();
        return ['html'=>view('admin.customer-order-profitability.partials.detail', $data + compact('snapshot'))->render(),'metrics'=>['net_profit'=>$data['net'],'profitability_percentage'=>$data['percentage']],'warnings'=>$data['warnings']];
    }
}

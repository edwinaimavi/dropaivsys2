<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingAgency;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierPurchaseOrderTrackingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.supplier-purchase-orders.trackings.index')->only('list');
        $this->middleware('can:admin.supplier-purchase-orders.trackings.store')->only('store');
        $this->middleware('can:admin.supplier-purchase-orders.trackings.update')->only('update');
        $this->middleware('can:admin.supplier-purchase-orders.trackings.destroy')->only('destroy');
    }

    public function list(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $supplierPurchaseOrder->load([
            'supplier:id,business_name,short_name',
            'shippingAgency:id,business_name,trade_name',
        ]);
        $trackings = $supplierPurchaseOrder->trackings()
            ->with(['createdBy:id,name', 'shippingAgency:id,business_name,trade_name'])
            ->orderByRaw('COALESCE(event_date, created_at) ASC')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'order' => [
                    'id' => $supplierPurchaseOrder->id,
                    'code' => $supplierPurchaseOrder->code,
                    'supplier' => $supplierPurchaseOrder->supplier?->short_name
                        ?? $supplierPurchaseOrder->supplier?->business_name
                        ?? '-',
                    'created_at' => $supplierPurchaseOrder->created_at?->format('d/m/Y H:i'),
                    'shipping_agency_id' => $supplierPurchaseOrder->shipping_agency_id,
                    'shipping_agency_name' => $supplierPurchaseOrder->shippingAgency?->trade_name
                        ?? $supplierPurchaseOrder->shippingAgency?->business_name,
                ],
                'statuses' => SupplierPurchaseOrderTracking::STATUSES,
                'current_status' => $trackings->last()?->status,
                'trackings' => $trackings->map(fn (SupplierPurchaseOrderTracking $tracking) => $this->serialize($tracking)),
            ],
        ]);
    }

    public function store(Request $request, SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $validated = $this->validated($request);
        $validated = $this->withCarrierName($validated);
        $path = $request->file('document')?->store('supplier-purchase-order-trackings', 'public');

        try {
            $tracking = DB::transaction(function () use ($validated, $path, $request, $supplierPurchaseOrder) {
                return $supplierPurchaseOrder->trackings()->create([
                    ...$validated,
                    'title' => SupplierPurchaseOrderTracking::STATUSES[$validated['status']],
                    'document_path' => $path,
                    'document_name' => $request->file('document')?->getClientOriginalName(),
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('public')->delete($path);
            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Seguimiento log&iacute;stico registrado correctamente.',
            'data' => $this->serialize($tracking->load([
                'createdBy:id,name',
                'shippingAgency:id,business_name,trade_name',
            ])),
        ], 201);
    }

    public function update(Request $request, SupplierPurchaseOrderTracking $tracking)
    {
        $validated = $this->validated($request);
        $validated = $this->withCarrierName($validated);
        $newPath = $request->file('document')?->store('supplier-purchase-order-trackings', 'public');
        $oldPath = $tracking->document_path;

        try {
            $tracking->update([
                ...$validated,
                'title' => SupplierPurchaseOrderTracking::STATUSES[$validated['status']],
                'document_path' => $newPath ?: $oldPath,
                'document_name' => $newPath ? $request->file('document')->getClientOriginalName() : $tracking->document_name,
                'updated_by' => Auth::id(),
            ]);
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        if ($newPath && $oldPath) Storage::disk('public')->delete($oldPath);

        return response()->json([
            'status' => 'success',
            'message' => 'Seguimiento actualizado.',
            'data' => $this->serialize($tracking->fresh([
                'createdBy:id,name',
                'shippingAgency:id,business_name,trade_name',
            ])),
        ]);
    }

    public function destroy(SupplierPurchaseOrderTracking $tracking)
    {
        $path = $tracking->document_path;
        $tracking->delete();
        if ($path) Storage::disk('public')->delete($path);

        return response()->json(['status' => 'success', 'message' => 'Evento de seguimiento eliminado.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'status' => ['required', Rule::in(array_keys(SupplierPurchaseOrderTracking::STATUSES))],
            'event_date' => ['nullable', 'date'],
            'estimated_date' => ['nullable', 'date'],
            'shipping_agency_id' => ['nullable', 'exists:shipping_agencies,id'],
            'carrier_name' => ['nullable', 'string', 'max:150'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }

    private function withCarrierName(array $validated): array
    {
        if (! empty($validated['shipping_agency_id'])) {
            $agency = ShippingAgency::query()->find($validated['shipping_agency_id']);
            $validated['carrier_name'] = $agency?->trade_name
                ?? $agency?->business_name
                ?? ($validated['carrier_name'] ?? null);
        }

        return $validated;
    }

    private function serialize(SupplierPurchaseOrderTracking $tracking): array
    {
        return [
            'id' => $tracking->id,
            'status' => $tracking->status,
            'title' => html_entity_decode($tracking->title ?: SupplierPurchaseOrderTracking::STATUSES[$tracking->status] ?? $tracking->status),
            'description' => $tracking->description,
            'event_date' => $tracking->event_date?->format('Y-m-d\TH:i'),
            'event_date_label' => $tracking->event_date?->format('d/m/Y H:i') ?? $tracking->created_at?->format('d/m/Y H:i'),
            'estimated_date' => $tracking->estimated_date?->format('Y-m-d'),
            'estimated_date_label' => $tracking->estimated_date?->format('d/m/Y'),
            'shipping_agency_id' => $tracking->shipping_agency_id,
            'carrier_name' => $tracking->shippingAgency?->trade_name
                ?? $tracking->shippingAgency?->business_name
                ?? $tracking->carrier_name,
            'tracking_number' => $tracking->tracking_number,
            'location' => $tracking->location,
            'document_name' => $tracking->document_name,
            'document_url' => $tracking->document_path ? Storage::disk('public')->url($tracking->document_path) : null,
            'created_by' => $tracking->createdBy?->name,
        ];
    }
}

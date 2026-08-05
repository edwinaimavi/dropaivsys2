<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierPurchaseOrder extends Model
{
    use SoftDeletes;

    public const DELIVERY_TYPE_AGENCY = 'agencia';
    public const DELIVERY_TYPE_WAREHOUSE_PICKUP = 'recojo_almacen';
    public const DELIVERY_TYPE_SUPPLIER_CARRIER = 'transportista_proveedor';

    public const DELIVERY_TYPES = [
        self::DELIVERY_TYPE_AGENCY,
        self::DELIVERY_TYPE_WAREHOUSE_PICKUP,
        self::DELIVERY_TYPE_SUPPLIER_CARRIER,
    ];

    public static function normalizeDeliveryType(?string $value): string
    {
        $normalized = mb_strtolower(\Illuminate\Support\Str::ascii(trim((string) $value)));
        $normalized = str_replace(['.', '-'], '', $normalized);
        $normalized = preg_replace('/\s+/', '_', $normalized);

        return match ($normalized) {
            'agencia_de_transporte', 'agencia_transporte', 'en_agencia', 'transporte' => self::DELIVERY_TYPE_AGENCY,
            'recojo_de_almacen' => self::DELIVERY_TYPE_WAREHOUSE_PICKUP,
            'transportista_del_proveedor' => self::DELIVERY_TYPE_SUPPLIER_CARRIER,
            default => $normalized,
        };
    }

    protected $fillable = [
        'code',
        'purchase_order_sequence',
        'purchase_order_year',
        'purchase_order_bank_code',
        'company_id',
        'supplier_id',
        'supplier_account_id',
        'currency_id',
        'customer_purchase_order_id',
        'quote_id',
        'market_study_id',
        'order_type',
        'payment_condition',
        'delivery_type',
        'transport_type',
        'shipping_address',
        'shipping_agency_id',
        'shipping_agency_branch_id',
        'shipping_agency_contact_id',
        'shipping_reference',
        'destination_ubigeo_id',
        'destination_text',
        'payment_method',
        'document_type',
        'affect_igv',
        'observations',
        'requested_by',
        'request_department',
        'authorized_by_name',
        'authorized_by_position',
        'delivery_text',
        'payment_terms_text',
        'purchase_instructions',
        'important_note',
        'subtotal',
        'igv',
        'grand_total',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'affect_igv' => 'boolean',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierAccount()
    {
        return $this->belongsTo(SupplierAccount::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function customerPurchaseOrder()
    {
        return $this->belongsTo(CustomerPurchaseOrder::class);
    }

    public function customerPurchaseOrders()
    {
        return $this->belongsToMany(
            CustomerPurchaseOrder::class,
            'supplier_purchase_order_customer_purchase_order'
        )->withTimestamps();
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function marketStudy()
    {
        return $this->belongsTo(MarketStudy::class);
    }

    public function destinationUbigeo()
    {
        return $this->belongsTo(Ubigeo::class, 'destination_ubigeo_id');
    }

    public function shippingAgency()
    {
        return $this->belongsTo(ShippingAgency::class);
    }

    public function shippingAgencyBranch()
    {
        return $this->belongsTo(ShippingAgencyBranch::class);
    }

    public function shippingAgencyContact()
    {
        return $this->belongsTo(ShippingAgencyContact::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierPurchaseOrderItem::class);
    }

    public function trackings()
    {
        return $this->hasMany(SupplierPurchaseOrderTracking::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function supplierQuotationDocument(): ?Document
    {
        $documents = $this->relationLoaded('documents')
            ? $this->documents
            : $this->documents()->with('documentType')->get();

        return $documents
            ->filter(function (Document $document) {
                if (strtoupper((string) $document->status) !== 'ACTIVE') {
                    return false;
                }

                $code = $this->normalizeDocumentType($document->documentType?->code);
                $description = $this->normalizeDocumentType($document->documentType?->description);

                return in_array($code, ['spoquote', 'supplierquote', 'cotizacionproveedor', 'cotizaciondelproveedor'], true)
                    || in_array($description, ['cotizacionproveedor', 'cotizaciondelproveedor'], true);
            })
            ->sortByDesc('id')
            ->first();
    }

    private function normalizeDocumentType(?string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii(trim((string) $value))));
    }

    public function warehouseEntries()
    {
        return $this->hasMany(WarehouseEntry::class);
    }

    public function refreshEntryStatus(): void
    {
        if (in_array($this->status, ['cancelled', 'invoiced'], true)) {
            return;
        }

        $orderedByItem = $this->items()
            ->where('status', '!=', 'deleted')
            ->select('id', 'quantity')
            ->get()
            ->mapWithKeys(fn (SupplierPurchaseOrderItem $item) => [
                $item->id => round((float) $item->quantity, 2),
            ]);

        if ($orderedByItem->isEmpty()) {
            $this->forceFill(['status' => 'registered'])->save();
            return;
        }

        $receivedByItem = DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->where('entries.supplier_purchase_order_id', $this->id)
            ->whereNull('entries.deleted_at')
            ->where('entries.status', 'registered')
            ->whereIn('items.supplier_purchase_order_item_id', $orderedByItem->keys()->all())
            ->where('items.status', '!=', 'deleted')
            ->groupBy('items.supplier_purchase_order_item_id')
            ->selectRaw('items.supplier_purchase_order_item_id, SUM(items.quantity) as received_quantity')
            ->pluck('received_quantity', 'supplier_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        $totalReceived = round((float) $receivedByItem->sum(), 2);
        $isComplete = $orderedByItem->every(function (float $ordered, $itemId) use ($receivedByItem) {
            return round((float) ($receivedByItem[$itemId] ?? 0), 2) >= $ordered;
        });

        $status = match (true) {
            $totalReceived <= 0 => 'registered',
            $isComplete => 'entered',
            default => 'partial_entered',
        };

        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->save();
        }
    }
}

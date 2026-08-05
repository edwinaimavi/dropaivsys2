<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CustomerPurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUS_ENTERED = 'entered';
    public const STATUS_ATTENDED = 'attended';
    public const STATUS_NOT_ATTENDED = 'not_attended';
    public const STATUS_IN_PURCHASE = 'in_purchase';
    public const STATUS_PARTIAL_PURCHASE = 'partial_purchase';
    public const STATUS_PARTIAL_ENTERED = 'partial_entered';

    public static function statusPresentation(?string $status): array
    {
        return match (strtolower(trim((string) $status))) {
            'registered' => ['label' => 'Registrada', 'class' => 'cop-status-registered', 'icon' => 'fa-clipboard'],
            'draft' => ['label' => 'Registrada', 'class' => 'cop-status-registered', 'icon' => 'fa-clipboard'],
            'sent' => ['label' => 'Enviada', 'class' => 'cop-status-partial-entered', 'icon' => 'fa-paper-plane'],
            'approved' => ['label' => 'Aprobada', 'class' => 'cop-status-attended', 'icon' => 'fa-check-circle'],
            'received' => ['label' => 'Recibida', 'class' => 'cop-status-entered', 'icon' => 'fa-box'],
            'in_purchase', 'en_compra' => ['label' => 'En compra', 'class' => 'cop-status-in-purchase', 'icon' => 'fa-shopping-cart'],
            'partial_purchase' => ['label' => 'Compra parcial', 'class' => 'cop-status-partial-purchase', 'icon' => 'fa-cart-plus'],
            'entered' => ['label' => 'Abastecida', 'class' => 'cop-status-entered', 'icon' => 'fa-warehouse'],
            'partial_entered' => ['label' => 'Ingreso parcial', 'class' => 'cop-status-partial-entered', 'icon' => 'fa-box'],
            'attended' => ['label' => 'Atendida', 'class' => 'cop-status-attended', 'icon' => 'fa-check'],
            'not_attended' => ['label' => 'No atendida', 'class' => 'cop-status-not-attended', 'icon' => 'fa-times'],
            'cancelled' => ['label' => 'Anulada', 'class' => 'cop-status-cancelled', 'icon' => 'fa-ban'],
            'completed' => ['label' => 'Completada', 'class' => 'cop-status-completed', 'icon' => 'fa-check-double'],
            'delivered' => ['label' => 'Entregada', 'class' => 'cop-status-completed', 'icon' => 'fa-truck'],
            'invoiced' => ['label' => 'Facturada', 'class' => 'cop-status-entered', 'icon' => 'fa-file-invoice'],
            default => ['label' => 'Sin estado', 'class' => 'cop-status-unknown', 'icon' => 'fa-minus'],
        };
    }

    private const IN_PURCHASE_STATUS_VALUES = [
        self::STATUS_IN_PURCHASE,
        'en_compra',
    ];

    protected $fillable = [
        'code',
        'company_id',
        'quote_id',
        'customer_id',
        'customer_branch_id',
        'order_type',
        'purchase_order_number',
        'currency_id',
        'notification_date',
        'delivery_start_date',
        'delivery_days',
        'delivery_end_date',
        'siaf_file_number',
        'acquisition_chart_number',
        'process_type',
        'billing_type',
        'affect_igv',
        'observations',
        'seller_type',
        'seller_user_id',
        'seller_dni',
        'seller_names',
        'seller_lastnames',
        'seller_full_name',
        'seller_phone',
        'seller_email',
        'seller_observation',
        'subtotal_exonerated',
        'subtotal_taxed',
        'igv',
        'grand_total',
        'status',
        'attention_result',
        'attention_observation',
        'attention_closed_at',
        'attention_closed_by',
        'attention_document_path',
        'attention_document_name',
        'attention_document_mime',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'notification_date' => 'date',
        'delivery_start_date' => 'date',
        'delivery_days' => 'integer',
        'delivery_end_date' => 'date',
        'affect_igv' => 'boolean',
        'subtotal_exonerated' => 'decimal:10',
        'subtotal_taxed' => 'decimal:10',
        'igv' => 'decimal:10',
        'grand_total' => 'decimal:10',
        'attention_closed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerBranch()
    {
        return $this->belongsTo(CustomerBranch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function items()
    {
        return $this->hasMany(CustomerPurchaseOrderItem::class);
    }

    public function supplierPurchaseOrders()
    {
        return $this->belongsToMany(
            SupplierPurchaseOrder::class,
            'supplier_purchase_order_customer_purchase_order'
        )->withTimestamps();
    }

    public function profitabilityAnalyses()
    {
        return $this->hasMany(CustomerOrderProfitabilityAnalysis::class);
    }

    public function scopeAvailableForSupplierPurchase($query)
    {
        return $query->whereHas('items', function ($items) {
            $items->where('customer_purchase_order_items.status', '!=', 'deleted')
                ->whereRaw('customer_purchase_order_items.quantity > COALESCE((
                    SELECT SUM(spoi.quantity)
                    FROM supplier_purchase_order_items spoi
                    INNER JOIN supplier_purchase_orders spo ON spo.id = spoi.supplier_purchase_order_id
                    WHERE spoi.customer_purchase_order_item_id = customer_purchase_order_items.id
                      AND spoi.status != ?
                      AND spo.status != ?
                      AND spo.deleted_at IS NULL
                ), 0)', ['deleted', 'cancelled']);
        });
    }

    public function isInPurchase(): bool
    {
        $status = strtolower(trim((string) $this->status));
        $status = str_replace([' ', '-'], '_', $status);

        return in_array($status, self::IN_PURCHASE_STATUS_VALUES, true);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sellerUser()
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function attentionClosedBy()
    {
        return $this->belongsTo(User::class, 'attention_closed_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function refreshSupplyStatus(): bool
    {
        if (in_array($this->status, [
            'cancelled',
            'delivered',
            'invoiced',
            self::STATUS_NOT_ATTENDED,
        ], true)) {
            return false;
        }

        $requestedByItem = $this->items()
            ->where('status', '!=', 'deleted')
            ->select('id', 'quantity')
            ->get()
            ->mapWithKeys(fn (CustomerPurchaseOrderItem $item) => [
                $item->id => round((float) $item->quantity, 2),
            ]);

        if ($requestedByItem->isEmpty()) {
            return $this->applySupplyStatus('registered');
        }

        $itemIds = $requestedByItem->keys()->all();

        $purchasedByItem = SupplierPurchaseOrderItem::query()
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'supplier_purchase_order_items.supplier_purchase_order_id')
            ->whereIn('supplier_purchase_order_items.customer_purchase_order_item_id', $itemIds)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', 'cancelled')
            ->where('supplier_purchase_order_items.status', '!=', 'deleted')
            ->groupBy('supplier_purchase_order_items.customer_purchase_order_item_id')
            ->selectRaw('supplier_purchase_order_items.customer_purchase_order_item_id, SUM(supplier_purchase_order_items.quantity) as purchased_quantity')
            ->pluck('purchased_quantity', 'customer_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        if ($purchasedByItem->isEmpty()) {
            return $this->applySupplyStatus('registered');
        }

        $enteredByItem = DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'items.supplier_purchase_order_item_id')
            ->join('supplier_purchase_orders as supplier_orders', 'supplier_orders.id', '=', 'supplier_items.supplier_purchase_order_id')
            ->whereIn('supplier_items.customer_purchase_order_item_id', $itemIds)
            ->whereNull('entries.deleted_at')
            ->whereNull('supplier_orders.deleted_at')
            ->where('entries.status', 'registered')
            ->where('supplier_orders.status', '!=', 'cancelled')
            ->where('items.status', '!=', 'deleted')
            ->where('supplier_items.status', '!=', 'deleted')
            ->groupBy('supplier_items.customer_purchase_order_item_id')
            ->selectRaw('supplier_items.customer_purchase_order_item_id, SUM(items.quantity) as entered_quantity')
            ->pluck('entered_quantity', 'customer_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        $status = self::supplyStatusFromQuantities(
            $requestedByItem->all(),
            $purchasedByItem->all(),
            $enteredByItem->all(),
            filled($this->attention_document_path)
        );

        return $this->applySupplyStatus($status);
    }

    public static function supplyStatusFromQuantities(
        array $requested,
        array $purchased,
        array $entered,
        bool $hasAttentionClosureDocument = false
    ): string
    {
        $allEntered = collect($requested)->every(fn ($quantity, $itemId) =>
            round((float) ($entered[$itemId] ?? 0), 2) >= round((float) $quantity, 2));
        if ($allEntered) {
            return $hasAttentionClosureDocument
                ? self::STATUS_ATTENDED
                : self::STATUS_ENTERED;
        }

        if (round((float) collect($entered)->sum(), 2) > 0) return self::STATUS_PARTIAL_ENTERED;

        return self::STATUS_IN_PURCHASE;
    }

    private function applySupplyStatus(string $status): bool
    {
        if ($this->status === $status) {
            return false;
        }

        $this->forceFill(['status' => $status])->save();

        return true;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceItem extends Model
{
    protected $fillable = [
        'electronic_invoice_id', 'article_id', 'quote_item_id', 'customer_purchase_order_item_id',
        'warehouse_entry_item_id', 'kardex_movement_id', 'item_number', 'product_code',
        'sunat_product_code', 'description', 'commercial_name', 'billing_name', 'unit_code',
        'unit_name', 'brand_name', 'presentation_name', 'lot_number', 'expiration_date', 'origin',
        'health_registration', 'quantity', 'unit_value', 'unit_price', 'discount_amount',
        'subtotal', 'igv_base', 'igv_amount', 'igv_percentage', 'tax_affectation_code',
        'tax_code', 'tax_name', 'tax_type_code', 'total_taxes', 'line_total', 'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'quantity' => 'decimal:4',
        'unit_value' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'igv_base' => 'decimal:2',
        'igv_amount' => 'decimal:2',
        'igv_percentage' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
    public function article() { return $this->belongsTo(Article::class); }
    public function quoteItem() { return $this->belongsTo(QuoteItem::class); }
    public function customerPurchaseOrderItem() { return $this->belongsTo(CustomerPurchaseOrderItem::class); }
    public function warehouseEntryItem() { return $this->belongsTo(WarehouseEntryItem::class); }
}

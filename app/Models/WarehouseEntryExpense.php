<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryExpense extends Model
{
    public const IGV_RATE = 18.0;

    public const IGV_DOCUMENT_TYPES = ['FACTURA', 'BOLETA'];

    protected $fillable = ['warehouse_entry_id', 'supplier_purchase_order_id', 'expense_category', 'cost_origin', 'expense_type', 'shipping_agency_id', 'provider_id', 'provider_ruc', 'provider_name', 'document_type', 'document_series', 'document_number', 'document_date', 'currency_id', 'amount', 'affects_igv', 'igv_rate', 'taxable_amount', 'igv_amount', 'total_amount', 'affects_inventory_cost', 'distribution_method', 'description', 'status', 'created_by', 'updated_by'];
    protected $casts = [
        'document_date' => 'date',
        'amount' => 'decimal:2',
        'affects_igv' => 'boolean',
        'igv_rate' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'igv_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'affects_inventory_cost' => 'boolean',
    ];

    public static function supportsIgv(?string $documentType): bool
    {
        return in_array(strtoupper(trim((string) $documentType)), self::IGV_DOCUMENT_TYPES, true);
    }

    public static function taxBreakdown(float $total, bool $affectsIgv, float $rate = self::IGV_RATE): array
    {
        $total = round($total, 2);
        $rate = $affectsIgv ? $rate : 0.0;
        $taxable = $affectsIgv ? round($total / (1 + ($rate / 100)), 2) : $total;

        return [
            'affects_igv' => $affectsIgv,
            'igv_rate' => $rate,
            'taxable_amount' => $taxable,
            'igv_amount' => $affectsIgv ? round($total - $taxable, 2) : 0.0,
            'total_amount' => $total,
        ];
    }

    public function warehouseEntry() { return $this->belongsTo(WarehouseEntry::class); }
    public function provider() { return $this->belongsTo(Supplier::class, 'provider_id'); }
    public function shippingAgency() { return $this->belongsTo(ShippingAgency::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function distributions() { return $this->hasMany(WarehouseEntryExpenseDistribution::class); }
    public function documents() { return $this->hasMany(WarehouseEntryExpenseDocument::class)->where('status', 'ACTIVE'); }
    public function invoiceDocuments() { return $this->documents()->where('document_type', WarehouseEntryExpenseDocument::TYPE_INVOICE); }
    public function paymentProofDocuments() { return $this->documents()->where('document_type', WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF); }
}

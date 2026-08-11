<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryExpense extends Model
{
    public const IGV_RATE = 18.0;

    public const IGV_DOCUMENT_TYPES = ['FACTURA', 'BOLETA'];

    public const OFFICIAL_DOCUMENT_TYPES = ['FACTURA', 'BOLETA', 'RECIBO_HONORARIOS'];

    public const UNOFFICIAL_DOCUMENT_TYPES = ['RECIBO_INTERNO', 'SIN_COMPROBANTE'];

    public const DOCUMENT_TYPES = [
        'FACTURA' => 'Factura',
        'BOLETA' => 'Boleta',
        'RECIBO_HONORARIOS' => 'Recibo por honorarios',
        'RECIBO_INTERNO' => 'Recibo interno',
        'SIN_COMPROBANTE' => 'Sin comprobante',
    ];

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
        return in_array(self::normalizeDocumentType($documentType), self::IGV_DOCUMENT_TYPES, true);
    }

    public static function isOfficialDocument(?string $documentType): bool
    {
        return in_array(self::normalizeDocumentType($documentType), self::OFFICIAL_DOCUMENT_TYPES, true);
    }

    public static function normalizeDocumentType(?string $documentType): string
    {
        $normalized = strtoupper(trim((string) $documentType));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            '' => 'SIN_COMPROBANTE',
            'RECIBO', 'RECIBO_INTERNO' => 'RECIBO_INTERNO',
            'RECIBO_POR_HONORARIOS', 'RECIBO_HONORARIO', 'RECIBO_HONORARIOS' => 'RECIBO_HONORARIOS',
            default => $normalized,
        };
    }

    public static function documentTypeLabel(?string $documentType): string
    {
        $normalized = self::normalizeDocumentType($documentType);

        return self::DOCUMENT_TYPES[$normalized] ?? ($normalized ?: self::DOCUMENT_TYPES['SIN_COMPROBANTE']);
    }

    public function getDocumentTypeAttribute(?string $value): string
    {
        return self::normalizeDocumentType($value);
    }

    public function setDocumentTypeAttribute(?string $value): void
    {
        $this->attributes['document_type'] = self::normalizeDocumentType($value);
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

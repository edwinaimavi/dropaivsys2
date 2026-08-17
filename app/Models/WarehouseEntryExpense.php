<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WarehouseEntryExpense extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PETTY_CASH = 'petty_cash';

    public const SOURCE_GENERAL_CASH = 'general_cash';

    public const SOURCE_BANK = 'bank';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_OBSERVED = 'observed';

    public const APPROVAL_REJECTED = 'rejected';

    public const DOCUMENT_CLASSIFICATION_OFFICIAL = 'official';

    public const DOCUMENT_CLASSIFICATION_NON_OFFICIAL = 'non_official';

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

    protected $fillable = ['warehouse_entry_id', 'supplier_purchase_order_id', 'source_type', 'petty_cash_expense_id', 'petty_cash_replenishment_id', 'general_cash_box_id', 'general_cash_movement_id', 'company_bank_account_id', 'bank_movement_id', 'document_classification', 'official_document_type', 'internal_document_type', 'exchanged_document_id', 'exchanged_at', 'payment_proof_path', 'official_document_path', 'expense_category', 'cost_origin', 'expense_type', 'shipping_agency_id', 'provider_id', 'provider_ruc', 'provider_name', 'document_type', 'document_series', 'document_number', 'document_date', 'currency_id', 'amount', 'affects_igv', 'igv_rate', 'taxable_amount', 'igv_amount', 'total_amount', 'affects_inventory_cost', 'distribution_method', 'description', 'status', 'approval_status', 'approval_observation', 'created_by', 'updated_by', 'approved_by', 'approved_at'];

    protected $casts = [
        'document_date' => 'date',
        'exchanged_at' => 'datetime',
        'amount' => 'decimal:2',
        'affects_igv' => 'boolean',
        'igv_rate' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'igv_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'affects_inventory_cost' => 'boolean',
        'approved_at' => 'datetime',
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

    public static function integrationColumnsAvailable(): bool
    {
        return Schema::hasTable('warehouse_entry_expenses')
            && collect([
                'source_type',
                'petty_cash_expense_id',
                'petty_cash_replenishment_id',
                'document_classification',
                'official_document_type',
                'internal_document_type',
                'exchanged_document_id',
                'exchanged_at',
                'payment_proof_path',
                'official_document_path',
            ])->every(fn (string $column) => Schema::hasColumn('warehouse_entry_expenses', $column));
    }

    public static function documentMetadata(?string $documentType): array
    {
        $type = self::normalizeDocumentType($documentType);
        $official = self::isOfficialDocument($type);

        return [
            'document_classification' => $official
                ? self::DOCUMENT_CLASSIFICATION_OFFICIAL
                : self::DOCUMENT_CLASSIFICATION_NON_OFFICIAL,
            'official_document_type' => $official ? match ($type) {
                'FACTURA' => 'factura',
                'BOLETA' => 'boleta',
                default => 'recibo_por_honorarios',
            } : null,
            'internal_document_type' => $official ? null : match ($type) {
                'RECIBO_INTERNO' => 'recibo_interno',
                default => 'sin_comprobante',
            },
        ];
    }

    public static function expenseTypeLabel(?string $expenseType): string
    {
        return match (strtolower(trim((string) $expenseType))) {
            'agency_freight', 'transport_agency', 'courier', 'shipping' => 'Flete de agencia',
            'pickup_transfer', 'agency_pickup_to_warehouse', 'agency_direct_to_warehouse',
            'supplier_warehouse_pickup', 'transfer_to_agency', 'truck', 'mobility',
            'delivery', 'transfer', 'flete', 'transporte', 'movilidad' => 'Recojo / traslado',
            default => 'Otros gastos',
        };
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

    public function warehouseEntry()
    {
        return $this->belongsTo(WarehouseEntry::class);
    }

    public function pettyCashExpense()
    {
        return $this->belongsTo(PettyCashExpense::class);
    }

    public function pettyCashReplenishment()
    {
        return $this->belongsTo(PettyCashReplenishment::class);
    }

    public function generalCashBox()
    {
        return $this->belongsTo(GeneralCashBox::class);
    }

    public function generalCashMovement()
    {
        return $this->belongsTo(GeneralCashMovement::class);
    }

    public function companyBankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function bankMovement()
    {
        return $this->belongsTo(BankMovement::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            self::SOURCE_PETTY_CASH => 'Caja Chica',
            self::SOURCE_GENERAL_CASH => 'Caja General',
            self::SOURCE_BANK => 'Banco',
            default => 'Manual / pendiente',
        };
    }

    public static function approvalLabel(?string $status): string
    {
        return match ($status) {
            self::APPROVAL_APPROVED => 'Aprobado',
            self::APPROVAL_OBSERVED => 'Observado',
            self::APPROVAL_REJECTED => 'Rechazado',
            default => 'Pendiente de aprobación',
        };
    }

    public function exchangedDocument()
    {
        return $this->belongsTo(Document::class, 'exchanged_document_id');
    }

    public function provider()
    {
        return $this->belongsTo(Supplier::class, 'provider_id');
    }

    public function shippingAgency()
    {
        return $this->belongsTo(ShippingAgency::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function distributions()
    {
        return $this->hasMany(WarehouseEntryExpenseDistribution::class);
    }

    public function documents()
    {
        return $this->hasMany(WarehouseEntryExpenseDocument::class)->where('status', 'ACTIVE');
    }

    public function invoiceDocuments()
    {
        return $this->documents()->where('document_type', WarehouseEntryExpenseDocument::TYPE_INVOICE);
    }

    public function paymentProofDocuments()
    {
        return $this->documents()->where('document_type', WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralCashExpense extends Model
{
    public const STATUS_REGISTERED = 'REGISTERED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_OBSERVED = 'OBSERVED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const CLASSIFICATION_OFFICIAL = 'OFFICIAL';

    public const CLASSIFICATION_UNSUPPORTED = 'WITHOUT_RECEIPT';

    public const OFFICIAL_DOCUMENT_TYPES = ['FACTURA', 'BOLETA', 'RECIBO_HONORARIOS'];

    public const DOCUMENT_TYPES = ['FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'RECIBO_INTERNO', 'SIN_COMPROBANTE'];

    protected $fillable = [
        'code', 'idempotency_key', 'general_cash_box_id', 'company_id', 'general_cash_movement_id',
        'expense_date', 'expense_type', 'supplier_id', 'person_name', 'identity_document', 'concept',
        'document_type', 'document_series', 'document_number', 'amount', 'affects_igv', 'taxable_base',
        'igv_amount', 'expense_classification', 'status', 'observation', 'created_by', 'updated_by',
        'approved_by', 'approved_at', 'observed_by', 'observed_at', 'observation_reason',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'expense_date' => 'date', 'amount' => 'decimal:4', 'affects_igv' => 'boolean',
        'taxable_base' => 'decimal:4', 'igv_amount' => 'decimal:4', 'approved_at' => 'datetime',
        'observed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function box()
    {
        return $this->belongsTo(GeneralCashBox::class, 'general_cash_box_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function movement()
    {
        return $this->belongsTo(GeneralCashMovement::class, 'general_cash_movement_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function observer()
    {
        return $this->belongsTo(User::class, 'observed_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public static function normalizeDocumentType(?string $type): string
    {
        $normalized = str_replace([' ', '-'], '_', strtoupper(trim((string) $type)));

        return match ($normalized) {
            'RECIBO', 'RECIBO_INTERNO' => 'RECIBO_INTERNO',
            'RECIBO_POR_HONORARIOS', 'RECIBO_HONORARIO' => 'RECIBO_HONORARIOS',
            '' => 'SIN_COMPROBANTE',
            default => $normalized,
        };
    }

    public static function isOfficial(?string $type): bool
    {
        return in_array(self::normalizeDocumentType($type), self::OFFICIAL_DOCUMENT_TYPES, true);
    }
}

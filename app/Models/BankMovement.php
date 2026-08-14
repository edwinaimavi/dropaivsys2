<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankMovement extends Model
{
    public const DIRECTION_IN = 'IN';

    public const DIRECTION_OUT = 'OUT';

    public const STATUS_REGISTERED = 'REGISTRADO';

    public const STATUS_RECONCILED = 'CONCILIADO';

    public const STATUS_CANCELLED = 'ANULADO';

    protected $fillable = [
        'code', 'company_bank_account_id', 'company_id', 'currency_id', 'original_currency_id', 'bank_transfer_id',
        'movement_date', 'movement_type', 'amount', 'original_amount', 'exchange_rate', 'original_exchange_rate', 'amount_pen', 'direction',
        'concept', 'description', 'operation_number', 'document_type', 'document_series',
        'document_number', 'document_date', 'file_path', 'file_original_name', 'file_mime_type',
        'file_size', 'source_type', 'source_id', 'source_code', 'source_description', 'status',
        'balance_after', 'reversal_of_id', 'idempotency_key', 'created_by', 'updated_by',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'movement_date' => 'datetime',
        'document_date' => 'date',
        'amount' => 'decimal:4',
        'original_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'original_exchange_rate' => 'decimal:6',
        'amount_pen' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'file_size' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function originalCurrency()
    {
        return $this->belongsTo(Currency::class, 'original_currency_id');
    }

    public function transfer()
    {
        return $this->belongsTo(BankTransfer::class, 'bank_transfer_id');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function reconciliationDetails()
    {
        return $this->hasMany(BankReconciliationMovement::class);
    }

    public static function typeLabel(?string $type): string
    {
        return [
            'SALDO_INICIAL' => 'Saldo inicial',
            'INGRESO' => 'Ingreso',
            'EGRESO' => 'Egreso',
            'TRANSFERENCIA_ENTRADA' => 'Transferencia recibida',
            'TRANSFERENCIA_SALIDA' => 'Transferencia enviada',
            'AJUSTE_POSITIVO' => 'Ajuste positivo',
            'AJUSTE_NEGATIVO' => 'Ajuste negativo',
            'REVERSA' => 'Reversa',
        ][$type] ?? (string) $type;
    }

    public static function sourceLabel(?string $source): string
    {
        return [
            'CUSTOMER_PAYMENT' => 'Cobro de cliente',
            'SUPPLIER_PAYMENT' => 'Pago a proveedor',
            'SUPPLIER_ADVANCE' => 'Anticipo a proveedor',
            'PETTY_CASH_OPENING' => 'Apertura de Caja Chica',
            'PETTY_CASH_REPLENISHMENT' => 'Reposición de Caja Chica',
            'PETTY_CASH_EXPENSE_EXCHANGE' => 'Canje de Caja Chica',
            'WAREHOUSE_ENTRY_EXPENSE' => 'Costo de Almacén',
            'WAREHOUSE_ENTRY_PAYMENT' => 'Ingreso de AlmacÃ©n',
            'BANK_TRANSFER' => 'Transferencia bancaria',
            'BANK_ADJUSTMENT' => 'Ajuste bancario',
            'BANK_OPENING_BALANCE' => 'Saldo inicial',
            'BANK_REVERSAL' => 'Reversa bancaria',
            'MANUAL' => 'Movimiento manual',
        ][$source] ?? (string) ($source ?: 'Sin origen');
    }
}

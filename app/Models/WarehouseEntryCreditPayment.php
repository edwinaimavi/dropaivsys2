<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryCreditPayment extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const PAYMENT_METHODS = [
        'transferencia' => 'Transferencia',
        'deposito' => 'Depósito',
        'cheque' => 'Cheque',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'warehouse_entry_id', 'supplier_purchase_order_id', 'supplier_id',
        'company_bank_account_id', 'purchase_currency_id', 'payment_currency_id',
        'applied_amount', 'amount', 'amount_pen', 'exchange_rate', 'payment_date',
        'payment_method', 'operation_number', 'proof_path', 'proof_original_name',
        'proof_mime_type', 'proof_size', 'observation', 'bank_movement_id',
        'idempotency_key', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'applied_amount' => 'decimal:4',
        'amount' => 'decimal:4',
        'amount_pen' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'payment_date' => 'date',
        'proof_size' => 'integer',
    ];

    public function warehouseEntry() { return $this->belongsTo(WarehouseEntry::class); }
    public function supplierPurchaseOrder() { return $this->belongsTo(SupplierPurchaseOrder::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function companyBankAccount() { return $this->belongsTo(CompanyBankAccount::class); }
    public function purchaseCurrency() { return $this->belongsTo(Currency::class, 'purchase_currency_id'); }
    public function paymentCurrency() { return $this->belongsTo(Currency::class, 'payment_currency_id'); }
    public function bankMovement() { return $this->belongsTo(BankMovement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPurchaseOrderAdvancePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_purchase_order_id', 'supplier_account_id', 'company_bank_account_id', 'currency_id', 'payment_date',
        'amount', 'amount_pen', 'exchange_rate', 'payment_method', 'operation_number',
        'proof_path', 'proof_original_name', 'proof_mime_type', 'proof_size', 'observation',
        'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:4',
        'amount_pen' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'proof_size' => 'integer',
    ];

    public function supplierPurchaseOrder() { return $this->belongsTo(SupplierPurchaseOrder::class); }
    public function supplierAccount() { return $this->belongsTo(SupplierAccount::class); }
    public function companyBankAccount() { return $this->belongsTo(CompanyBankAccount::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

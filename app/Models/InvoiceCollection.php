<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceCollection extends Model
{
    protected $fillable = [
        'electronic_invoice_id', 'customer_purchase_order_id', 'company_bank_account_id',
        'currency_id', 'collection_date', 'amount', 'invoice_currency_amount', 'exchange_rate',
        'operation_number', 'proof_file_path', 'proof_original_name', 'proof_mime_type',
        'proof_size', 'observation', 'bank_movement_id', 'idempotency_key', 'created_by',
    ];

    protected $casts = [
        'collection_date' => 'date',
        'amount' => 'decimal:10',
        'invoice_currency_amount' => 'decimal:10',
        'exchange_rate' => 'decimal:10',
        'proof_size' => 'integer',
    ];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
    public function customerPurchaseOrder() { return $this->belongsTo(CustomerPurchaseOrder::class); }
    public function account() { return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id'); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function bankMovement() { return $this->belongsTo(BankMovement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

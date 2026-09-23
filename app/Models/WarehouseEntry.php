<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseEntry extends Model
{
    use SoftDeletes;

    public const PAYMENT_METHODS = [
        'deposito_cuenta' => 'Depósito en cuenta',
        'transferencia' => 'Transferencia bancaria',
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'yape_plin' => 'Yape / Plin',
        'otro' => 'Otro',
    ];

    public const PAYMENT_CONDITIONS = [
        'contado' => 'Contado',
        'credito' => 'Crédito',
    ];

    protected $fillable = [
        'entry_number',
        'supplier_purchase_order_id',
        'entry_mode',
        'warehouse_id',
        'company_id',
        'supplier_id',
        'customer_id',
        'currency_id',
        'exchange_rate',
        'purchase_order_number',
        'document_type',
        'sunat_document_type_code',
        'document_series',
        'document_number',
        'document_date',
        'movement_date',
        'payment_method',
        'payment_condition',
        'credit_days',
        'generate_account_payable',
        'payable_amount',
        'expected_payment_date',
        'payment_company_bank_account_id',
        'bank_payment_date',
        'bank_payment_operation_number',
        'bank_payment_exchange_rate',
        'bank_payment_proof_path',
        'bank_payment_proof_original_name',
        'bank_payment_proof_mime_type',
        'bank_payment_proof_size',
        'bank_payment_observation',
        'bank_payment_negative_balance_confirmed',
        'seller_name',
        'affect_igv',
        'guide_series',
        'guide_number',
        'guide_ruc',
        'observations',
        'subtotal',
        'igv',
        'grand_total',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'movement_date' => 'datetime',
        'expected_payment_date' => 'date',
        'bank_payment_date' => 'date',
        'generate_account_payable' => 'boolean',
        'credit_days' => 'integer',
        'bank_payment_negative_balance_confirmed' => 'boolean',
        'affect_igv' => 'boolean',
        'payable_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'bank_payment_exchange_rate' => 'decimal:6',
        'bank_payment_proof_size' => 'integer',
    ];

    public static function sunatDocumentTypeCode(?string $documentType): ?string
    {
        return match (strtoupper(trim((string) $documentType))) {
            'FACTURA', '01' => '01',
            'BOLETA', '03' => '03',
            default => null,
        };
    }

    public function supplierPurchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bankPaymentAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'payment_company_bank_account_id');
    }

    public function bankPaymentMovement()
    {
        return $this->hasOne(BankMovement::class, 'source_id')
            ->where('source_type', 'WAREHOUSE_ENTRY_PAYMENT')
            ->where('status', '!=', BankMovement::STATUS_CANCELLED)
            ->latestOfMany();
    }

    public function bankPaymentMovements()
    {
        return $this->hasMany(BankMovement::class, 'source_id')
            ->where('source_type', 'WAREHOUSE_ENTRY_PAYMENT');
    }

    public function creditPayments()
    {
        return $this->hasMany(WarehouseEntryCreditPayment::class)
            ->where('status', WarehouseEntryCreditPayment::STATUS_ACTIVE)
            ->orderBy('payment_date')
            ->orderBy('id');
    }

    public function creditPaymentHistory()
    {
        return $this->hasMany(WarehouseEntryCreditPayment::class)
            ->withTrashed()
            ->orderBy('payment_date')
            ->orderBy('id');
    }

    public function paymentDocuments()
    {
        return $this->hasMany(WarehouseEntryPaymentDocument::class)
            ->latest('id');
    }

    public function items()
    {
        return $this->hasMany(WarehouseEntryItem::class)->where('status', '!=', 'deleted');
    }

    public function allocations()
    {
        return $this->hasMany(WarehouseEntryItemAllocation::class)
            ->where('status', 'active');
    }

    public function customerPurchaseOrders()
    {
        return $this->belongsToMany(
            CustomerPurchaseOrder::class,
            'customer_purchase_order_warehouse_entry'
        )->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function lotDocuments()
    {
        return $this->hasMany(WarehouseEntryItemLotDocument::class);
    }

    public function expenses()
    {
        return $this->hasMany(WarehouseEntryExpense::class)->where('status', 'ACTIVE');
    }
}

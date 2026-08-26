<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseEntryPaymentDocument extends Model
{
    use SoftDeletes;

    public const TYPE_PAYMENT_PROOF = 'payment_proof';

    protected $fillable = [
        'warehouse_entry_id', 'bank_movement_id',
        'supplier_purchase_order_advance_payment_id', 'warehouse_entry_credit_payment_id',
        'document_type', 'file_path', 'original_name', 'mime_type', 'size',
        'observation', 'uploaded_by', 'deleted_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function warehouseEntry()
    {
        return $this->belongsTo(WarehouseEntry::class);
    }

    public function bankMovement()
    {
        return $this->belongsTo(BankMovement::class);
    }

    public function advancePayment()
    {
        return $this->belongsTo(
            SupplierPurchaseOrderAdvancePayment::class,
            'supplier_purchase_order_advance_payment_id'
        );
    }

    public function creditPayment()
    {
        return $this->belongsTo(WarehouseEntryCreditPayment::class, 'warehouse_entry_credit_payment_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoicePayment extends Model
{
    protected $fillable = ['electronic_invoice_id', 'payment_type', 'quota_number', 'amount', 'due_date', 'payment_date', 'status'];

    protected $casts = ['due_date' => 'date', 'payment_date' => 'date', 'amount' => 'decimal:2'];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
}

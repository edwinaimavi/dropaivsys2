<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceStatusHistory extends Model
{
    protected $fillable = ['electronic_invoice_id', 'previous_status', 'new_status', 'sunat_code', 'description', 'changed_by', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
    public function user() { return $this->belongsTo(User::class, 'changed_by'); }
}

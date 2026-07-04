<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceLegend extends Model
{
    protected $fillable = ['electronic_invoice_id', 'code', 'description', 'value'];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
}

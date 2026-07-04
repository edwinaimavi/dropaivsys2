<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceFile extends Model
{
    protected $fillable = ['electronic_invoice_id', 'file_type', 'file_name', 'file_path', 'mime_type', 'size', 'source', 'is_generated', 'created_by'];

    protected $casts = ['is_generated' => 'boolean', 'size' => 'integer'];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

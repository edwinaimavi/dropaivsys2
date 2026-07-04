<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceRelatedDocument extends Model
{
    protected $fillable = ['electronic_invoice_id', 'relation_type', 'document_type', 'serie', 'number', 'full_number', 'description', 'issue_date', 'file_path'];

    protected $casts = ['issue_date' => 'date'];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
}

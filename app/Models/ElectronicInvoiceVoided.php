<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceVoided extends Model
{
    protected $table = 'electronic_invoice_voided';

    protected $fillable = [
        'electronic_invoice_id', 'voided_number', 'communication_date', 'document_date', 'reason',
        'ticket', 'sunat_status', 'sunat_code', 'sunat_description', 'api_payload', 'api_response',
        'xml_path', 'cdr_path', 'status', 'created_by',
    ];

    protected $casts = [
        'communication_date' => 'date',
        'document_date' => 'date',
        'api_payload' => 'array',
        'api_response' => 'array',
    ];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}

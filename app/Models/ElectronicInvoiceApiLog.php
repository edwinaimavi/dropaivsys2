<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicInvoiceApiLog extends Model
{
    protected $fillable = [
        'electronic_invoice_id', 'provider', 'operation', 'method', 'endpoint', 'request_headers',
        'request_payload', 'response_status', 'response_payload', 'success', 'message',
        'error_code', 'error_message', 'executed_by', 'executed_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'success' => 'boolean',
        'executed_at' => 'datetime',
    ];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
    public function executor() { return $this->belongsTo(User::class, 'executed_by'); }
}

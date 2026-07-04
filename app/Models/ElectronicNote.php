<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElectronicNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'electronic_invoice_id', 'company_id', 'customer_id', 'document_type', 'serie',
        'correlativo', 'full_number', 'note_type_code', 'reason', 'issue_date', 'currency_code',
        'subtotal', 'igv_amount', 'total_amount', 'api_payload', 'api_response', 'xml_path',
        'pdf_path', 'cdr_path', 'sunat_status', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal' => 'decimal:2',
        'igv_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'api_payload' => 'array',
        'api_response' => 'array',
    ];

    public function invoice() { return $this->belongsTo(ElectronicInvoice::class, 'electronic_invoice_id'); }
    public function company() { return $this->belongsTo(Company::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}

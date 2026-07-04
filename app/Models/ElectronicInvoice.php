<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElectronicInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'quote_id',
        'customer_purchase_order_id',
        'warehouse_entry_id',
        'currency_id',
        'serie_id',
        'document_type',
        'serie',
        'correlativo',
        'full_number',
        'issue_date',
        'issue_time',
        'due_date',
        'operation_type',
        'currency_code',
        'payment_type',
        'payment_method',
        'payment_condition',
        'client_document_type',
        'client_document_number',
        'client_name',
        'client_address',
        'client_ubigeo',
        'client_department',
        'client_province',
        'client_district',
        'client_email',
        'client_phone',
        'company_ruc',
        'company_business_name',
        'company_trade_name',
        'company_address',
        'company_ubigeo',
        'company_department',
        'company_province',
        'company_district',
        'purchase_order_number',
        'siaf_number',
        'process_number',
        'contract_number',
        'delivery_note',
        'taxable_amount',
        'exonerated_amount',
        'unaffected_amount',
        'free_amount',
        'discount_total',
        'subtotal',
        'igv_amount',
        'isc_amount',
        'icbper_amount',
        'other_charges',
        'total_taxes',
        'total_amount',
        'total_text',
        'api_payload',
        'api_response',
        'api_provider',
        'api_endpoint',
        'api_sent_at',
        'api_status_code',
        'api_success',
        'api_message',
        'sunat_status',
        'sunat_code',
        'sunat_description',
        'sunat_notes',
        'cdr_ticket',
        'hash',
        'xml_name',
        'pdf_name',
        'cdr_name',
        'xml_path',
        'pdf_path',
        'cdr_path',
        'status',
        'is_sent_to_sunat',
        'is_voided',
        'voided_at',
        'voided_reason',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'api_payload' => 'array',
        'api_response' => 'array',
        'api_sent_at' => 'datetime',
        'api_success' => 'boolean',
        'sunat_notes' => 'array',
        'is_sent_to_sunat' => 'boolean',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
        'taxable_amount' => 'decimal:2',
        'exonerated_amount' => 'decimal:2',
        'unaffected_amount' => 'decimal:2',
        'free_amount' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'igv_amount' => 'decimal:2',
        'isc_amount' => 'decimal:2',
        'icbper_amount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function quote() { return $this->belongsTo(Quote::class); }
    public function customerPurchaseOrder() { return $this->belongsTo(CustomerPurchaseOrder::class); }
    public function warehouseEntry() { return $this->belongsTo(WarehouseEntry::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function serie() { return $this->belongsTo(ElectronicInvoiceSeries::class, 'serie_id'); }
    public function items() { return $this->hasMany(ElectronicInvoiceItem::class); }
    public function payments() { return $this->hasMany(ElectronicInvoicePayment::class); }
    public function legends() { return $this->hasMany(ElectronicInvoiceLegend::class); }
    public function relatedDocuments() { return $this->hasMany(ElectronicInvoiceRelatedDocument::class); }
    public function files() { return $this->hasMany(ElectronicInvoiceFile::class); }
    public function apiLogs() { return $this->hasMany(ElectronicInvoiceApiLog::class); }
    public function statusHistories() { return $this->hasMany(ElectronicInvoiceStatusHistory::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElectronicInvoiceSeries extends Model
{
    use SoftDeletes;

    protected $table = 'electronic_invoice_series';

    protected $fillable = [
        'company_id',
        'document_type',
        'serie',
        'current_number',
        'next_number',
        'environment',
        'description',
        'is_default',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'current_number' => 'integer',
        'next_number' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function invoices()
    {
        return $this->hasMany(ElectronicInvoice::class, 'serie_id');
    }
}

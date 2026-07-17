<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElectronicInvoiceSetting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'provider',
        'environment',
        'api_base_url',
        'api_token',
        'user_token',
        'ruc',
        'business_name',
        'trade_name',
        'address',
        'ubigeo',
        'department',
        'province',
        'district',
        'sol_user',
        'sol_password',
        'certificate_path',
        'logo_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_token' => 'encrypted',
        'user_token' => 'encrypted',
        'sol_user' => 'encrypted',
        'sol_password' => 'encrypted',
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
}

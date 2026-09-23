<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAccountingSetting extends Model
{
    protected $fillable = [
        'company_id',
        'inventory_account_id',
        'receipt_offset_account_id',
        'cost_of_sales_account_id',
        'adjustment_gain_account_id',
        'adjustment_loss_account_id',
        'transfer_clearing_account_id',
        'opening_offset_account_id',
        'updated_by',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

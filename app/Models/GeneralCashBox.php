<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneralCashBox extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    protected $fillable = [
        'code', 'company_id', 'currency_id', 'name', 'description', 'responsible_user_id',
        'current_balance', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['current_balance' => 'decimal:4'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function movements()
    {
        return $this->hasMany(GeneralCashMovement::class);
    }

    public function expenses()
    {
        return $this->hasMany(GeneralCashExpense::class);
    }

    public function reconciliations()
    {
        return $this->hasMany(GeneralCashReconciliation::class);
    }
}

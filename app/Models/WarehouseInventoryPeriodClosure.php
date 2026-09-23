<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseInventoryPeriodClosure extends Model
{
    public const ACTION_CLOSE = 'close';

    public const ACTION_REOPEN = 'reopen';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'year',
        'month',
        'action',
        'reason',
        'summary',
        'created_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'summary' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isClose(): bool
    {
        return $this->action === self::ACTION_CLOSE;
    }

    public function isReopen(): bool
    {
        return $this->action === self::ACTION_REOPEN;
    }
}

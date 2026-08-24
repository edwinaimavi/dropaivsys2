<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseKardexRecalculation extends Model
{
    protected $fillable = [
        'warehouse_id',
        'article_id',
        'date_from',
        'date_to',
        'stocks_processed',
        'movements_processed',
        'notes',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

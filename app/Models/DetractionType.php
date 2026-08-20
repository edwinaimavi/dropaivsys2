<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetractionType extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    protected $fillable = [
        'appendix',
        'code',
        'name',
        'description',
        'percentage',
        'legal_reference',
        'effective_from',
        'status',
    ];

    protected $casts = [
        'percentage' => 'decimal:4',
        'effective_from' => 'date',
    ];

    public function warehouseEntryExpenses()
    {
        return $this->hasMany(WarehouseEntryExpense::class);
    }
}

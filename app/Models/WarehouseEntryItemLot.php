<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryItemLot extends Model
{
    protected $fillable = [
        'warehouse_entry_item_id', 'lot_code', 'quantity', 'expiration_date',
        'manufacturing_date', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'expiration_date' => 'date',
        'manufacturing_date' => 'date',
    ];

    public function warehouseEntryItem()
    {
        return $this->belongsTo(WarehouseEntryItem::class);
    }
}

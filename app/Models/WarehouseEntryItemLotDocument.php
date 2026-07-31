<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryItemLotDocument extends Model
{
    protected $fillable = [
        'warehouse_entry_id', 'warehouse_entry_item_id', 'warehouse_entry_item_lot_id',
        'document_type', 'description', 'file_path', 'original_name', 'mime_type',
        'file_size', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function warehouseEntry() { return $this->belongsTo(WarehouseEntry::class); }
    public function warehouseEntryItem() { return $this->belongsTo(WarehouseEntryItem::class); }
    public function warehouseEntryItemLot() { return $this->belongsTo(WarehouseEntryItemLot::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
}

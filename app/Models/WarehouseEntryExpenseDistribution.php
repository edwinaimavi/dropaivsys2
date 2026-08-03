<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryExpenseDistribution extends Model
{
    protected $fillable = ['warehouse_entry_expense_id', 'warehouse_entry_item_id', 'warehouse_entry_item_lot_id', 'distributed_amount'];
    protected $casts = ['distributed_amount' => 'decimal:2'];
    public function expense() { return $this->belongsTo(WarehouseEntryExpense::class, 'warehouse_entry_expense_id'); }
    public function item() { return $this->belongsTo(WarehouseEntryItem::class, 'warehouse_entry_item_id'); }
    public function lot() { return $this->belongsTo(WarehouseEntryItemLot::class, 'warehouse_entry_item_lot_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryExpenseDocument extends Model
{
    protected $fillable = ['warehouse_entry_expense_id', 'document_type', 'description', 'file_path', 'original_name', 'mime_type', 'file_size', 'status', 'created_by', 'updated_by'];
    public function expense() { return $this->belongsTo(WarehouseEntryExpense::class, 'warehouse_entry_expense_id'); }
}

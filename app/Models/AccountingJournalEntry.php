<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingJournalEntry extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'warehouse_kardex_movement_id',
        'entry_date',
        'cuo',
        'correlative',
        'source',
        'description',
        'total_debit',
        'total_credit',
        'status',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movement()
    {
        return $this->belongsTo(WarehouseKardexMovement::class, 'warehouse_kardex_movement_id');
    }

    public function lines()
    {
        return $this->hasMany(AccountingJournalEntryLine::class, 'journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingJournalEntryLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'memo',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }
}

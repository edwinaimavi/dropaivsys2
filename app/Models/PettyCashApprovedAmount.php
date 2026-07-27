<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashApprovedAmount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'currency_id',
        'amount',
        'approved_at',
        'approved_by_user_id',
        'active',
        'observation',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
    public function approvedBy() { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function approvedAmountHistories() { return $this->morphMany(ApprovedAmountHistory::class, 'approvable'); }
    public function pettyCashBoxes() { return $this->hasMany(PettyCashBox::class, 'approved_amount_id'); }
}

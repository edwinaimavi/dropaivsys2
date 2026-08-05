<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentIssuer extends Model
{
    protected $fillable = [
        'ruc', 'business_name', 'trade_name', 'address', 'status', 'condition',
        'source', 'api_response', 'last_lookup_at', 'created_by', 'updated_by',
    ];

    protected $casts = ['api_response' => 'array', 'last_lookup_at' => 'datetime'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }
}

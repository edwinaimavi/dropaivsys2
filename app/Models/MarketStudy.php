<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketStudy extends Model
{
    use SoftDeletes;

    protected $table = 'market_studies';

    protected $fillable = [
        'code',
        'description',
        'reference_terms',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater()
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function documents()
    {
        return $this->morphMany(
            Document::class,
            'documentable'
        );
    }

    public function items()
    {
        return $this->hasMany(
            MarketStudyItem::class
        );
    }

    public function quotes()
    {
        return $this->hasMany(MarketStudyQuote::class);
    }

    public function winners()
{
    return $this->hasManyThrough(
        \App\Models\MarketStudyItemWinner::class,
        \App\Models\MarketStudyItem::class,
        'market_study_id',      // FK en market_study_items
        'market_study_item_id', // FK en market_study_item_winners
        'id',                   // PK en market_studies
        'id'                    // PK en market_study_items
    );
}
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SunatCatalogItem extends Model
{
    protected $fillable = ['catalog_code', 'item_code', 'description', 'short_name', 'extra_data', 'status'];

    protected $casts = ['extra_data' => 'array'];
}

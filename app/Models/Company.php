<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{


    protected $fillable = [
        'business_name',
        'trade_name',
        'ruc',
        'email',
        'phone',
        'address',
        'logo',
        'status',
    ];
}

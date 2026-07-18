<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $fillable = ['user_id', 'theme_mode'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

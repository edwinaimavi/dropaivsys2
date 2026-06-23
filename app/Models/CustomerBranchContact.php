<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerBranchContact extends Model
{
    use HasFactory;

    protected $fillable = [

        'customer_branch_id',

        'contact_name',
        'position',

        'phone',
        'email',

        'address',
        'reference',

        'status',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(
            CustomerBranch::class,
            'customer_branch_id'
        );
    }

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
}

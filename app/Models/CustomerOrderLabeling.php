<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerOrderLabeling extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'customer_purchase_order_id',
        'company_id',
        'customer_id',
        'customer_branch_id',
        'invoice_number',
        'guide_number',
        'boxes_count',
        'total_quantity',
        'status',
        'pdf_path',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'boxes_count' => 'integer',
        'total_quantity' => 'decimal:2',
    ];

    public function customerPurchaseOrder()
    {
        return $this->belongsTo(CustomerPurchaseOrder::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerBranch()
    {
        return $this->belongsTo(CustomerBranch::class);
    }

    public function boxes()
    {
        return $this->hasMany(CustomerOrderLabelingBox::class)->orderBy('box_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElectronicInvoiceItemDispatchAllocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'electronic_invoice_item_id',
        'warehouse_dispatch_item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $allocation) {
            if ((float) $allocation->quantity <= 0) {
                throw new \InvalidArgumentException('La cantidad asignada al despacho debe ser mayor a cero.');
            }
        });
    }

    public function invoiceItem()
    {
        return $this->belongsTo(ElectronicInvoiceItem::class, 'electronic_invoice_item_id');
    }

    public function dispatchItem()
    {
        return $this->belongsTo(WarehouseDispatchItem::class, 'warehouse_dispatch_item_id');
    }
}

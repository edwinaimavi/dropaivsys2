<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPurchaseOrderTracking extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'registered' => 'Orden registrada',
        'sent_to_supplier' => 'Enviada al proveedor',
        'supplier_confirmed' => 'Confirmada por proveedor',
        'preparing' => 'En preparaci&oacute;n',
        'delivered_to_carrier' => 'Entregada a courier/agencia',
        'in_transit' => 'En tr&aacute;nsito',
        'arrived_destination' => 'Lleg&oacute; a destino',
        'received_office' => 'Recibida en oficina/agencia',
        'received_warehouse' => 'Recibida en almac&eacute;n',
        'observed' => 'Observada',
        'cancelled' => 'Cancelada',
    ];

    protected $fillable = [
        'supplier_purchase_order_id', 'status', 'title', 'description', 'event_date',
        'estimated_date', 'carrier_name', 'tracking_number', 'location',
        'document_path', 'document_name', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'estimated_date' => 'date',
    ];

    public function supplierPurchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class);
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

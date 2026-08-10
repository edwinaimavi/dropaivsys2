<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPurchaseOrderTracking extends Model
{
    use SoftDeletes;

    public const STATUS_RECEIVED_WAREHOUSE = 'received_warehouse';

    public const MAIN_FLOW = [
        'registered',
        'sent_to_supplier',
        'supplier_confirmed',
        'preparing',
        'delivered_to_carrier',
        'in_transit',
        'arrived_destination',
        'received_office',
        self::STATUS_RECEIVED_WAREHOUSE,
    ];

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
        'estimated_date', 'shipping_agency_id', 'carrier_name', 'tracking_number', 'location',
        'document_path', 'document_name', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'estimated_date' => 'date',
    ];

    public static function logisticsSummary(iterable $statuses): array
    {
        $statusCodes = collect($statuses)
            ->map(fn ($status) => strtolower(trim((string) $status)))
            ->filter()
            ->values();
        $isComplete = $statusCodes->contains(self::STATUS_RECEIVED_WAREHOUSE);
        $currentStatusCode = $statusCodes->last();
        $currentMainStatus = $statusCodes->reverse()->first(
            fn (string $status) => in_array($status, self::MAIN_FLOW, true)
        );
        $currentMainIndex = $currentMainStatus === null
            ? -1
            : array_search($currentMainStatus, self::MAIN_FLOW, true);
        $missingCodes = $isComplete
            ? []
            : array_slice(self::MAIN_FLOW, ((int) $currentMainIndex) + 1);

        return [
            'is_complete' => $isComplete,
            'current_status_code' => $currentStatusCode,
            'current_status' => $currentStatusCode
                ? self::plainStatusLabel($currentStatusCode)
                : 'Sin seguimiento',
            'missing_step_codes' => $missingCodes,
            'missing_steps' => array_map(self::plainStatusLabel(...), $missingCodes),
        ];
    }

    public static function plainStatusLabel(string $status): string
    {
        return html_entity_decode(
            self::STATUSES[$status] ?? $status,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }

    public function supplierPurchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class);
    }

    public function shippingAgency()
    {
        return $this->belongsTo(ShippingAgency::class);
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

<?php

namespace App\Models;

use App\Services\WarehouseInventoryPeriodClosureService;
use Illuminate\Database\Eloquent\Model;

class WarehouseKardexMovement extends Model
{

    private const CLOSED_PERIOD_PROTECTED_FIELDS = [
        'company_id',
        'warehouse_stock_id',
        'warehouse_id',
        'sunat_establishment_code_snapshot',
        'article_id',
        'article_code_snapshot',
        'article_description_snapshot',
        'sunat_existence_type_code_snapshot',
        'existence_catalog_code_snapshot',
        'existence_code_snapshot',
        'unit_id',
        'sunat_unit_code_snapshot',
        'unit_description_snapshot',
        'valuation_method_code_snapshot',
        'valuation_method_description_snapshot',
        'presentation_id',
        'brand_id',
        'lot_number',
        'expiration_date',
        'origin',
        'cost_type',
        'movement_date',
        'movement_type',
        'operation_type',
        'sunat_operation_type_code_snapshot',
        'source_type',
        'source_id',
        'source_item_type',
        'source_item_id',
        'document_type',
        'document_date_snapshot',
        'sunat_document_type_code_snapshot',
        'document_series',
        'document_number',
        'related_party_type',
        'related_party_id',
        'related_party_name',
        'quantity_in',
        'quantity_out',
        'balance_quantity',
        'unit_cost',
        'total_cost_in',
        'total_cost_out',
        'average_unit_cost',
        'balance_total_cost',
        'currency_id',
        'exchange_rate',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            $movement->assertPeriodIsOpen();
        });

        static::updating(function (self $movement): void {
            $dirty = array_keys($movement->getDirty());
            if (array_intersect($dirty, self::CLOSED_PERIOD_PROTECTED_FIELDS) !== []) {
                $movement->assertPeriodIsOpen();
            }
        });

        static::deleting(function (self $movement): void {
            $movement->assertPeriodIsOpen();
        });
    }

    private function assertPeriodIsOpen(): void
    {
        if (! $this->company_id || ! $this->warehouse_id || ! $this->movement_date) {
            return;
        }

        app(WarehouseInventoryPeriodClosureService::class)->assertOpen(
            (int) $this->company_id,
            (int) $this->warehouse_id,
            $this->movement_date
        );
    }

    protected $fillable = [
        'movement_number',
        'company_id',
        'warehouse_stock_id',
        'warehouse_id',
        'sunat_establishment_code_snapshot',
        'article_id',
        'article_code_snapshot',
        'article_description_snapshot',
        'sunat_existence_type_code_snapshot',
        'existence_catalog_code_snapshot',
        'existence_code_snapshot',
        'unit_id',
        'sunat_unit_code_snapshot',
        'unit_description_snapshot',
        'valuation_method_code_snapshot',
        'valuation_method_description_snapshot',
        'presentation_id',
        'brand_id',
        'lot_number',
        'expiration_date',
        'origin',
        'cost_type',
        'movement_date',
        'movement_type',
        'operation_type',
        'sunat_operation_type_code_snapshot',
        'source_type',
        'source_id',
        'source_item_type',
        'source_item_id',
        'source_key',
        'document_type',
        'document_date_snapshot',
        'sunat_document_type_code_snapshot',
        'document_series',
        'document_number',
        'related_party_type',
        'related_party_id',
        'related_party_name',
        'quantity_in',
        'quantity_out',
        'balance_quantity',
        'unit_cost',
        'total_cost_in',
        'total_cost_out',
        'average_unit_cost',
        'balance_total_cost',
        'currency_id',
        'exchange_rate',
        'accounting_cuo_snapshot',
        'accounting_entry_correlative_snapshot',
        'accounting_posted_at',
        'observations',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'document_date_snapshot' => 'date',
        'movement_date' => 'datetime',
        'quantity_in' => 'decimal:4',
        'quantity_out' => 'decimal:4',
        'balance_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost_in' => 'decimal:2',
        'total_cost_out' => 'decimal:2',
        'average_unit_cost' => 'decimal:6',
        'balance_total_cost' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'accounting_posted_at' => 'datetime',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function accountingJournalEntry()
    {
        return $this->hasOne(AccountingJournalEntry::class, 'warehouse_kardex_movement_id');
    }

    public function stock()
    {
        return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function sourceItem()
    {
        return $this->morphTo(__FUNCTION__, 'source_item_type', 'source_item_id');
    }
}

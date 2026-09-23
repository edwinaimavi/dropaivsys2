<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'wdi_dispatch_item_stock_unique';

    public function up(): void
    {
        $hasDuplicates = DB::table('warehouse_dispatch_items')
            ->select([
                'warehouse_dispatch_id',
                'customer_purchase_order_item_id',
                'warehouse_stock_id',
            ])
            ->groupBy([
                'warehouse_dispatch_id',
                'customer_purchase_order_item_id',
                'warehouse_stock_id',
            ])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Existen detalles de despacho duplicados para la combinación despacho, ítem OC Cliente y stock. No se aplicó el índice UNIQUE.'
            );
        }

        Schema::table('warehouse_dispatch_items', function (Blueprint $table) {
            $table->unique([
                'warehouse_dispatch_id',
                'customer_purchase_order_item_id',
                'warehouse_stock_id',
            ], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_dispatch_items', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};

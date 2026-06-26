<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_purchase_order_customer_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_purchase_order_id');
            $table->foreignId('customer_purchase_order_id');
            $table->timestamps();

            $table->unique([
                'supplier_purchase_order_id',
                'customer_purchase_order_id',
            ], 'spo_cpo_unique');

            $table->foreign(
                'supplier_purchase_order_id',
                'spo_cpo_supplier_order_fk'
            )->references('id')
                ->on('supplier_purchase_orders')
                ->cascadeOnDelete();

            $table->foreign(
                'customer_purchase_order_id',
                'spo_cpo_customer_order_fk'
            )->references('id')
                ->on('customer_purchase_orders')
                ->cascadeOnDelete();
        });

        DB::table('supplier_purchase_orders')
            ->whereNotNull('customer_purchase_order_id')
            ->orderBy('id')
            ->select(['id', 'customer_purchase_order_id'])
            ->chunkById(500, function ($orders) {
                $now = now();
                $rows = $orders
                    ->map(fn ($order) => [
                        'supplier_purchase_order_id' => $order->id,
                        'customer_purchase_order_id' => $order->customer_purchase_order_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::table('supplier_purchase_order_customer_purchase_order')
                    ->insertOrIgnore($rows);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_order_customer_purchase_order');
    }
};

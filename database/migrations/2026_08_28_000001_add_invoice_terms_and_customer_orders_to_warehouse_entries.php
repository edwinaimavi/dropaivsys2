<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouse_entries', 'credit_days')) {
            Schema::table('warehouse_entries', function (Blueprint $table) {
                $table->unsignedInteger('credit_days')->nullable()->after('payment_condition');
            });
        }

        if (! Schema::hasTable('customer_purchase_order_warehouse_entry')) {
            Schema::create('customer_purchase_order_warehouse_entry', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_entry_id');
                $table->foreignId('customer_purchase_order_id');
                $table->timestamps();

                $table->unique(
                    ['warehouse_entry_id', 'customer_purchase_order_id'],
                    'cpo_we_unique'
                );
                $table->foreign('warehouse_entry_id', 'cpo_we_entry_fk')
                    ->references('id')->on('warehouse_entries')->cascadeOnDelete();
                $table->foreign('customer_purchase_order_id', 'cpo_we_order_fk')
                    ->references('id')->on('customer_purchase_orders')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('warehouse_entry_item_allocations')) {
            DB::table('warehouse_entry_item_allocations')
                ->whereNotNull('customer_purchase_order_id')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->select(['id', 'warehouse_entry_id', 'customer_purchase_order_id'])
                ->chunkById(500, function ($allocations) {
                    $now = now();
                    $rows = $allocations
                        ->map(fn ($allocation) => [
                            'warehouse_entry_id' => $allocation->warehouse_entry_id,
                            'customer_purchase_order_id' => $allocation->customer_purchase_order_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->unique(fn ($row) => $row['warehouse_entry_id'].'-'.$row['customer_purchase_order_id'])
                        ->values()
                        ->all();

                    if ($rows) {
                        DB::table('customer_purchase_order_warehouse_entry')->insertOrIgnore($rows);
                    }
                }, 'id');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_purchase_order_warehouse_entry');

        if (Schema::hasColumn('warehouse_entries', 'credit_days')) {
            Schema::table('warehouse_entries', function (Blueprint $table) {
                $table->dropColumn('credit_days');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->string('entry_mode', 30)
                ->default('supplier_order')
                ->after('supplier_purchase_order_id')
                ->index();
        });

        Schema::create('warehouse_entry_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_entry_id');
            $table->foreignId('warehouse_entry_item_id');
            $table->foreignId('customer_purchase_order_id')->nullable();
            $table->foreignId('customer_purchase_order_item_id')->nullable();
            $table->foreignId('supplier_purchase_order_id')->nullable();
            $table->foreignId('supplier_purchase_order_item_id')->nullable();
            $table->foreignId('article_id');
            $table->decimal('quantity_allocated', 15, 4);
            $table->decimal('unit_cost', 15, 6)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('allocation_type', 30);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('warehouse_entry_id', 'weia_entry_fk')->references('id')->on('warehouse_entries')->cascadeOnDelete();
            $table->foreign('warehouse_entry_item_id', 'weia_entry_item_fk')->references('id')->on('warehouse_entry_items')->cascadeOnDelete();
            $table->foreign('customer_purchase_order_id', 'weia_customer_order_fk')->references('id')->on('customer_purchase_orders')->nullOnDelete();
            $table->foreign('customer_purchase_order_item_id', 'weia_customer_item_fk')->references('id')->on('customer_purchase_order_items')->nullOnDelete();
            $table->foreign('supplier_purchase_order_id', 'weia_supplier_order_fk')->references('id')->on('supplier_purchase_orders')->nullOnDelete();
            $table->foreign('supplier_purchase_order_item_id', 'weia_supplier_item_fk')->references('id')->on('supplier_purchase_order_items')->nullOnDelete();
            $table->foreign('article_id', 'weia_article_fk')->references('id')->on('articles')->restrictOnDelete();

            $table->index(['customer_purchase_order_item_id', 'status'], 'weia_customer_item_status_idx');
            $table->index(['supplier_purchase_order_item_id', 'status'], 'weia_supplier_item_status_idx');
            $table->index(['warehouse_entry_item_id', 'status'], 'weia_entry_item_status_idx');
            $table->index(['article_id', 'allocation_type'], 'weia_article_type_idx');
        });

        DB::table('warehouse_entries')
            ->whereNull('supplier_purchase_order_id')
            ->update(['entry_mode' => 'supplier_invoice']);

        $this->backfillLegacyAllocations();

        if (Schema::hasTable('warehouse_entry_credit_payments')) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
                    $table->dropForeign('wecp_spo_fk');
                });
            }
            Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('supplier_purchase_order_id')->nullable()->change();
            });
            if (DB::connection()->getDriverName() !== 'sqlite') {
                Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
                $table->foreign('supplier_purchase_order_id', 'wecp_spo_fk')
                    ->references('id')->on('supplier_purchase_orders')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('warehouse_entry_credit_payments')) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
                    $table->dropForeign('wecp_spo_fk');
                });
            }
            Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('supplier_purchase_order_id')->nullable(false)->change();
            });
            if (DB::connection()->getDriverName() !== 'sqlite') {
                Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
                $table->foreign('supplier_purchase_order_id', 'wecp_spo_fk')
                    ->references('id')->on('supplier_purchase_orders')->cascadeOnDelete();
                });
            }
        }

        Schema::dropIfExists('warehouse_entry_item_allocations');
        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->dropIndex(['entry_mode']);
            $table->dropColumn('entry_mode');
        });
    }

    private function backfillLegacyAllocations(): void
    {
        DB::table('warehouse_entry_items as entry_items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'entry_items.warehouse_entry_id')
            ->leftJoin('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'entry_items.supplier_purchase_order_item_id')
            ->leftJoin('customer_purchase_order_items as customer_items', 'customer_items.id', '=', 'supplier_items.customer_purchase_order_item_id')
            ->where('entry_items.status', '!=', 'deleted')
            ->orderBy('entry_items.id')
            ->select([
                'entry_items.id',
                'entry_items.warehouse_entry_id',
                'entry_items.article_id',
                'entry_items.quantity',
                'entry_items.unit_price',
                'entry_items.real_unit_cost',
                'entry_items.supplier_purchase_order_item_id',
                'entries.supplier_purchase_order_id as entry_supplier_order_id',
                'entries.created_by',
                'entries.updated_by',
                'supplier_items.supplier_purchase_order_id as item_supplier_order_id',
                'supplier_items.customer_purchase_order_item_id',
                'customer_items.customer_purchase_order_id',
            ])
            ->chunkById(500, function ($items) {
                $now = now();
                $rows = $items->map(function ($item) use ($now) {
                    $customerItemId = $item->customer_purchase_order_item_id;
                    $supplierOrderId = $item->item_supplier_order_id ?: $item->entry_supplier_order_id;
                    $type = $customerItemId
                        ? 'customer_order'
                        : ($supplierOrderId ? 'supplier_order' : 'free_stock');
                    $unitCost = (float) ($item->unit_price ?: 0);

                    return [
                        'warehouse_entry_id' => $item->warehouse_entry_id,
                        'warehouse_entry_item_id' => $item->id,
                        'customer_purchase_order_id' => $item->customer_purchase_order_id,
                        'customer_purchase_order_item_id' => $customerItemId,
                        'supplier_purchase_order_id' => $supplierOrderId,
                        'supplier_purchase_order_item_id' => $item->supplier_purchase_order_item_id,
                        'article_id' => $item->article_id,
                        'quantity_allocated' => $item->quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => round((float) $item->quantity * $unitCost, 2),
                        'allocation_type' => $type,
                        'status' => 'active',
                        'created_by' => $item->created_by,
                        'updated_by' => $item->updated_by,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                if ($rows) {
                    DB::table('warehouse_entry_item_allocations')->insert($rows);
                }
            }, 'entry_items.id', 'id');
    }
};

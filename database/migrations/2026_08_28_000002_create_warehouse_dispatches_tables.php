<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number', 30)->unique();
            $table->foreignId('customer_purchase_order_id')->constrained('customer_purchase_orders')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->dateTime('dispatch_date');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observation')->nullable();
            $table->string('document_type', 60)->nullable();
            $table->string('document_number', 80)->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->string('status', 30)->default('registered');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_purchase_order_id', 'status'], 'wd_order_status_idx');
            $table->index(['warehouse_id', 'dispatch_date'], 'wd_warehouse_date_idx');
        });

        Schema::create('warehouse_dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_dispatch_id')->constrained('warehouse_dispatches')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_purchase_order_item_id');
            $table->foreignId('warehouse_stock_id')->constrained('warehouse_stocks')->restrictOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained('presentations')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 6)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->foreignId('kardex_movement_id')->nullable()->constrained('warehouse_kardex_movements')->nullOnDelete();
            $table->string('status', 30)->default('registered');
            $table->timestamps();

            $table->foreign('customer_purchase_order_item_id', 'wdi_customer_order_item_fk')
                ->references('id')->on('customer_purchase_order_items')->restrictOnDelete();

            $table->index(['customer_purchase_order_item_id', 'status'], 'wdi_customer_item_status_idx');
            $table->index(['warehouse_stock_id', 'status'], 'wdi_stock_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_dispatch_items');
        Schema::dropIfExists('warehouse_dispatches');
    }
};

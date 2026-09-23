<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_return_id')->constrained('customer_returns')->restrictOnDelete();
            $table->foreignId('warehouse_dispatch_item_id')->constrained('warehouse_dispatch_items')->restrictOnDelete();
            $table->foreignId('warehouse_stock_id')->constrained('warehouse_stocks')->restrictOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained('presentations')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('lot_number_snapshot', 100)->nullable();
            $table->date('expiration_date_snapshot')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost_snapshot', 15, 6)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->foreignId('kardex_movement_id')->nullable()->constrained('warehouse_kardex_movements')->nullOnDelete();
            $table->foreignId('reversal_kardex_movement_id')->nullable()->constrained('warehouse_kardex_movements')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->unique(['customer_return_id', 'warehouse_dispatch_item_id'], 'cri_return_dispatch_item_unique');
            $table->index(['warehouse_dispatch_item_id', 'status'], 'cri_dispatch_item_status_idx');
            $table->index(['warehouse_stock_id', 'status'], 'cri_stock_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_return_items');
    }
};

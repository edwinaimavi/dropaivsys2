<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_entry_id')->constrained('warehouse_entries')->cascadeOnDelete();
            $table->foreignId('supplier_purchase_order_item_id')->nullable()
                ->constrained('supplier_purchase_order_items')->nullOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->string('article_code')->nullable();
            $table->string('billing_name_snapshot');
            $table->text('note')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained('presentations')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('origin', 100)->nullable();
            $table->string('cost_type', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->decimal('ordered_quantity', 15, 2)->default(0);
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index('supplier_purchase_order_item_id', 'wei_spo_item_id_idx');
            $table->index('article_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entry_items');
    }
};

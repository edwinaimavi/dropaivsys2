<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_purchase_order_id')
                ->constrained('supplier_purchase_orders')
                ->cascadeOnDelete();

            $table->foreignId('article_id')
                ->constrained('articles')
                ->restrictOnDelete();

            $table->foreignId('market_study_item_id')
                ->nullable()
                ->constrained('market_study_items')
                ->nullOnDelete();

            $table->foreignId('quote_item_id')
                ->nullable()
                ->constrained('quote_items')
                ->nullOnDelete();

            $table->foreignId('customer_purchase_order_item_id')
                ->nullable();

            $table->string('article_code')->nullable();
            $table->string('billing_name_snapshot');
            $table->text('note')->nullable();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->foreignId('presentation_id')
                ->nullable()
                ->constrained('presentations')
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->string('origin', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('cost_type', 100)->nullable();
            $table->decimal('reference_purchase_price', 15, 2)->default(0);
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->string('status', 30)->default('active');

            $table->timestamps();

            $table->index('status');
            $table->index('article_id');

            $table->foreign(
                'customer_purchase_order_item_id',
                'spo_items_customer_order_item_fk'
            )
                ->references('id')
                ->on('customer_purchase_order_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_order_items');
    }
};

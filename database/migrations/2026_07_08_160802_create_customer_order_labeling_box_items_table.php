<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_order_labeling_box_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_order_labeling_box_id');
            $table->unsignedBigInteger('customer_purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('article_id')->nullable();
            $table->string('article_code')->nullable();
            $table->text('description');
            $table->string('unit_name')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('lot')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('origin')->nullable();
            $table->timestamps();

            $table->index('customer_order_labeling_box_id', 'colbi_box_idx');
            $table->index('customer_purchase_order_item_id', 'colbi_cpo_item_idx');

            $table->foreign('customer_order_labeling_box_id', 'colbi_box_fk')
                ->references('id')
                ->on('customer_order_labeling_boxes')
                ->cascadeOnDelete();
            $table->foreign('customer_purchase_order_item_id', 'colbi_cpo_item_fk')
                ->references('id')
                ->on('customer_purchase_order_items')
                ->nullOnDelete();
            $table->foreign('article_id', 'colbi_article_fk')
                ->references('id')
                ->on('articles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_order_labeling_box_items');
    }
};

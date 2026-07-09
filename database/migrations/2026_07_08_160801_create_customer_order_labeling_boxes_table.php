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
        Schema::create('customer_order_labeling_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_labeling_id')
                ->constrained('customer_order_labelings')
                ->cascadeOnDelete();
            $table->unsignedInteger('box_number');
            $table->string('box_label', 20);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index('customer_order_labeling_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_order_labeling_boxes');
    }
};

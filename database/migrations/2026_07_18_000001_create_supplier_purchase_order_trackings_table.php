<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_purchase_order_trackings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_purchase_order_id');
            $table->string('status', 40);
            $table->string('title', 150)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->date('estimated_date')->nullable();
            $table->string('carrier_name', 150)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_purchase_order_id', 'event_date'], 'spo_tracking_order_event_index');
            $table->index('status');
            $table->foreign('supplier_purchase_order_id', 'spo_trackings_order_fk')
                ->references('id')->on('supplier_purchase_orders')->cascadeOnDelete();
            $table->foreign('created_by', 'spo_trackings_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'spo_trackings_updated_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_order_trackings');
    }
};

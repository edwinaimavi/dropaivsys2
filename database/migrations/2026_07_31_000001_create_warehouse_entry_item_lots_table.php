<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_entry_item_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_entry_item_id')->constrained('warehouse_entry_items')->cascadeOnDelete();
            $table->string('lot_code', 100);
            $table->decimal('quantity', 15, 4);
            $table->date('expiration_date')->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_entry_item_id', 'status'], 'wei_lots_item_status_idx');
            $table->index('lot_code');
            $table->index('expiration_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entry_item_lots');
    }
};

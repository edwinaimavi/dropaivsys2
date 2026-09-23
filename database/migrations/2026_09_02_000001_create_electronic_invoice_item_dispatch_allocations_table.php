<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electronic_invoice_item_dispatch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_item_id');
            $table->foreignId('warehouse_dispatch_item_id');
            $table->decimal('quantity', 15, 4);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['electronic_invoice_item_id', 'warehouse_dispatch_item_id'],
                'eiida_invoice_dispatch_unique'
            );
            $table->index(
                ['warehouse_dispatch_item_id', 'deleted_at'],
                'eiida_dispatch_active_idx'
            );
            $table->foreign('electronic_invoice_item_id', 'eiida_invoice_item_fk')
                ->references('id')
                ->on('electronic_invoice_items')
                ->cascadeOnDelete();
            $table->foreign('warehouse_dispatch_item_id', 'eiida_dispatch_item_fk')
                ->references('id')
                ->on('warehouse_dispatch_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_invoice_item_dispatch_allocations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_entry_item_lot_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_entry_id');
            $table->unsignedBigInteger('warehouse_entry_item_id');
            $table->unsignedBigInteger('warehouse_entry_item_lot_id');
            $table->string('document_type', 50);
            $table->string('description')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_entry_id', 'wei_lot_docs_entry_fk')
                ->references('id')->on('warehouse_entries')->cascadeOnDelete();
            $table->foreign('warehouse_entry_item_id', 'wei_lot_docs_item_fk')
                ->references('id')->on('warehouse_entry_items')->cascadeOnDelete();
            $table->foreign('warehouse_entry_item_lot_id', 'wei_lot_docs_lot_fk')
                ->references('id')->on('warehouse_entry_item_lots')->cascadeOnDelete();
            $table->foreign('created_by', 'wei_lot_docs_created_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'wei_lot_docs_updated_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['warehouse_entry_item_lot_id', 'status'], 'wei_lot_docs_lot_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entry_item_lot_documents');
    }
};

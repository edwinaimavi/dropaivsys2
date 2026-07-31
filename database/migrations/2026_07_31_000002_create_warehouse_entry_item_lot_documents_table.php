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
            $table->foreignId('warehouse_entry_id')->constrained('warehouse_entries')->cascadeOnDelete();
            $table->foreignId('warehouse_entry_item_id')->constrained('warehouse_entry_items')->cascadeOnDelete();
            $table->foreignId('warehouse_entry_item_lot_id')->constrained('warehouse_entry_item_lots')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('description')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_entry_item_lot_id', 'status'], 'wei_lot_docs_lot_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entry_item_lot_documents');
    }
};

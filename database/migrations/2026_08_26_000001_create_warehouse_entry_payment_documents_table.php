<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_entry_payment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_entry_id');
            $table->foreignId('bank_movement_id')->nullable();
            $table->foreignId('supplier_purchase_order_advance_payment_id')->nullable();
            $table->foreignId('warehouse_entry_credit_payment_id')->nullable();
            $table->string('document_type', 50)->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('uploaded_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('warehouse_entry_id', 'wepd_entry_fk')
                ->references('id')->on('warehouse_entries')->restrictOnDelete();
            $table->foreign('bank_movement_id', 'wepd_movement_fk')
                ->references('id')->on('bank_movements')->nullOnDelete();
            $table->foreign('supplier_purchase_order_advance_payment_id', 'wepd_advance_fk')
                ->references('id')->on('supplier_purchase_order_advance_payments')->nullOnDelete();
            $table->foreign('warehouse_entry_credit_payment_id', 'wepd_credit_fk')
                ->references('id')->on('warehouse_entry_credit_payments')->nullOnDelete();
            $table->foreign('uploaded_by', 'wepd_uploaded_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by', 'wepd_deleted_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['warehouse_entry_id', 'deleted_at'], 'wepd_entry_deleted_idx');
            $table->index(['bank_movement_id', 'deleted_at'], 'wepd_movement_deleted_idx');
            $table->index(['supplier_purchase_order_advance_payment_id', 'deleted_at'], 'wepd_advance_deleted_idx');
            $table->index(['warehouse_entry_credit_payment_id', 'deleted_at'], 'wepd_credit_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entry_payment_documents');
    }
};

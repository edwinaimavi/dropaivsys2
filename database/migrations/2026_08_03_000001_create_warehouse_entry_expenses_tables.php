<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouse_entry_items', 'additional_cost')) {
            Schema::table('warehouse_entry_items', function (Blueprint $table) {
                $table->decimal('additional_cost', 15, 2)->default(0)->after('line_total');
                $table->decimal('real_unit_cost', 15, 6)->default(0)->after('additional_cost');
            });
        }

        if (! Schema::hasTable('warehouse_entry_expenses')) Schema::create('warehouse_entry_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expense_type', 50);
            $table->foreignId('provider_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('provider_ruc', 20)->nullable();
            $table->string('provider_name')->nullable();
            $table->string('document_type', 50)->nullable();
            $table->string('document_series', 20)->nullable();
            $table->string('document_number', 50)->nullable();
            $table->date('document_date')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->boolean('affects_inventory_cost')->default(false);
            $table->string('distribution_method', 30)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['warehouse_entry_id', 'status']);
            $table->index(['provider_ruc', 'document_type', 'document_series', 'document_number'], 'wee_document_lookup_idx');
        });

        if (! Schema::hasTable('warehouse_entry_expense_distributions')) Schema::create('warehouse_entry_expense_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_entry_expense_id');
            $table->unsignedBigInteger('warehouse_entry_item_id');
            $table->unsignedBigInteger('warehouse_entry_item_lot_id')->nullable();
            $table->decimal('distributed_amount', 15, 2);
            $table->timestamps();
            $table->unique(['warehouse_entry_expense_id', 'warehouse_entry_item_id', 'warehouse_entry_item_lot_id'], 'weed_expense_item_lot_unique');
            $table->foreign('warehouse_entry_expense_id', 'weed_expense_fk')->references('id')->on('warehouse_entry_expenses')->cascadeOnDelete();
            $table->foreign('warehouse_entry_item_id', 'weed_item_fk')->references('id')->on('warehouse_entry_items')->cascadeOnDelete();
            $table->foreign('warehouse_entry_item_lot_id', 'weed_lot_fk')->references('id')->on('warehouse_entry_item_lots')->nullOnDelete();
        });

        if (! Schema::hasTable('warehouse_entry_expense_documents')) Schema::create('warehouse_entry_expense_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_entry_expense_id');
            $table->string('document_type', 50)->nullable();
            $table->string('description')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('warehouse_entry_expense_id', 'weedoc_expense_fk')->references('id')->on('warehouse_entry_expenses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entry_expense_documents');
        Schema::dropIfExists('warehouse_entry_expense_distributions');
        Schema::dropIfExists('warehouse_entry_expenses');
        Schema::table('warehouse_entry_items', fn (Blueprint $table) => $table->dropColumn(['additional_cost', 'real_unit_cost']));
    }
};

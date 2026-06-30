<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 30)->unique();
            $table->foreignId('supplier_purchase_order_id')->nullable()
                ->constrained('supplier_purchase_orders')->nullOnDelete();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('purchase_order_number', 50)->nullable();
            $table->string('document_type', 100)->nullable();
            $table->string('document_series', 20)->nullable();
            $table->string('document_number', 50)->nullable();
            $table->date('document_date')->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->string('payment_condition', 100)->nullable();
            $table->boolean('generate_account_payable')->default(false);
            $table->decimal('payable_amount', 15, 2)->default(0);
            $table->date('expected_payment_date')->nullable();
            $table->string('seller_name')->nullable();
            $table->boolean('affect_igv')->default(true);
            $table->string('guide_series', 20)->nullable();
            $table->string('guide_number', 50)->nullable();
            $table->string('guide_ruc', 20)->nullable();
            $table->text('observations')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('igv', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('status', 30)->default('registered');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('entry_number');
            $table->index('supplier_purchase_order_id');
            $table->index('warehouse_id');
            $table->index('supplier_id');
            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entries');
    }
};

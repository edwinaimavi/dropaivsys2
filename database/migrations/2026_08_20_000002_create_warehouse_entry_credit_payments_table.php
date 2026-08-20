<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_entry_credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_entry_id');
            $table->foreignId('supplier_purchase_order_id');
            $table->foreignId('supplier_id');
            $table->foreignId('company_bank_account_id');
            $table->foreignId('purchase_currency_id');
            $table->foreignId('payment_currency_id');
            $table->decimal('applied_amount', 18, 4);
            $table->decimal('amount', 18, 4);
            $table->decimal('amount_pen', 18, 4);
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->date('payment_date');
            $table->string('payment_method', 50);
            $table->string('operation_number', 100);
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime_type', 100)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('bank_movement_id')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_entry_id', 'wecp_entry_fk')
                ->references('id')->on('warehouse_entries')->restrictOnDelete();
            $table->foreign('supplier_purchase_order_id', 'wecp_spo_fk')
                ->references('id')->on('supplier_purchase_orders')->restrictOnDelete();
            $table->foreign('supplier_id', 'wecp_supplier_fk')
                ->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('company_bank_account_id', 'wecp_bank_fk')
                ->references('id')->on('company_bank_accounts')->restrictOnDelete();
            $table->foreign('purchase_currency_id', 'wecp_currency_fk')
                ->references('id')->on('currencies')->restrictOnDelete();
            $table->foreign('payment_currency_id', 'wecp_pay_currency_fk')
                ->references('id')->on('currencies')->restrictOnDelete();
            $table->foreign('bank_movement_id', 'wecp_movement_fk')
                ->references('id')->on('bank_movements')->restrictOnDelete();
            $table->foreign('created_by', 'wecp_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'wecp_updated_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['warehouse_entry_id', 'status'], 'we_credit_payments_entry_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_entry_credit_payments');
    }
};

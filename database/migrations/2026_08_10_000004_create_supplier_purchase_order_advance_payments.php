<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_purchase_order_advance_payments')) {
            Schema::create('supplier_purchase_order_advance_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_purchase_order_id');
                $table->unsignedBigInteger('supplier_account_id')->nullable();
                $table->unsignedBigInteger('currency_id');
                $table->date('payment_date');
                $table->decimal('amount', 18, 4);
                $table->decimal('amount_pen', 18, 4);
                $table->decimal('exchange_rate', 18, 6)->nullable();
                $table->string('payment_method', 50);
                $table->string('operation_number', 100)->nullable();
                $table->string('proof_path')->nullable();
                $table->string('proof_original_name')->nullable();
                $table->string('proof_mime_type', 100)->nullable();
                $table->unsignedBigInteger('proof_size')->nullable();
                $table->text('observation')->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('supplier_purchase_order_advance_payments', function (Blueprint $table) {
            $table->foreign('supplier_purchase_order_id', 'spo_adv_pay_order_fk')
                ->references('id')->on('supplier_purchase_orders')->cascadeOnDelete();
            $table->foreign('supplier_account_id', 'spo_adv_pay_account_fk')
                ->references('id')->on('supplier_accounts')->nullOnDelete();
            $table->foreign('currency_id', 'spo_adv_pay_currency_fk')
                ->references('id')->on('currencies')->restrictOnDelete();
            $table->foreign('created_by', 'spo_adv_pay_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'spo_adv_pay_updated_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['supplier_purchase_order_id', 'status'], 'spo_advance_payment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_order_advance_payments');
    }
};

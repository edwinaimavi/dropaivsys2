<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_order_advance_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_currency_id')
                ->nullable()
                ->after('company_bank_account_id');
            $table->decimal('applied_amount', 18, 4)
                ->nullable()
                ->after('payment_date');
            $table->foreign('purchase_currency_id', 'spo_adv_pay_purchase_currency_fk')
                ->references('id')
                ->on('currencies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_order_advance_payments', function (Blueprint $table) {
            $table->dropForeign('spo_adv_pay_purchase_currency_fk');
            $table->dropColumn(['purchase_currency_id', 'applied_amount']);
        });
    }
};

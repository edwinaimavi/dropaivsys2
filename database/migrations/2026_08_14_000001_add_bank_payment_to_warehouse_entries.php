<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->foreignId('payment_company_bank_account_id')->nullable()
                ->after('expected_payment_date')
                ->constrained('company_bank_accounts')->restrictOnDelete();
            $table->date('bank_payment_date')->nullable()->after('payment_company_bank_account_id');
            $table->string('bank_payment_operation_number', 100)->nullable()->after('bank_payment_date');
            $table->decimal('bank_payment_exchange_rate', 18, 6)->nullable()->after('bank_payment_operation_number');
            $table->string('bank_payment_proof_path')->nullable()->after('bank_payment_exchange_rate');
            $table->string('bank_payment_proof_original_name')->nullable()->after('bank_payment_proof_path');
            $table->string('bank_payment_proof_mime_type', 100)->nullable()->after('bank_payment_proof_original_name');
            $table->unsignedBigInteger('bank_payment_proof_size')->nullable()->after('bank_payment_proof_mime_type');
            $table->text('bank_payment_observation')->nullable()->after('bank_payment_proof_size');
            $table->boolean('bank_payment_negative_balance_confirmed')->default(false)
                ->after('bank_payment_observation');
        });

        Schema::table('bank_movements', function (Blueprint $table) {
            $table->foreignId('original_currency_id')->nullable()->after('currency_id')
                ->constrained('currencies')->restrictOnDelete();
            $table->decimal('original_amount', 18, 4)->nullable()->after('amount');
            $table->decimal('original_exchange_rate', 18, 6)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('bank_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_currency_id');
            $table->dropColumn(['original_amount', 'original_exchange_rate']);
        });

        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_company_bank_account_id');
            $table->dropColumn([
                'bank_payment_date',
                'bank_payment_operation_number',
                'bank_payment_exchange_rate',
                'bank_payment_proof_path',
                'bank_payment_proof_original_name',
                'bank_payment_proof_mime_type',
                'bank_payment_proof_size',
                'bank_payment_observation',
                'bank_payment_negative_balance_confirmed',
            ]);
        });
    }
};

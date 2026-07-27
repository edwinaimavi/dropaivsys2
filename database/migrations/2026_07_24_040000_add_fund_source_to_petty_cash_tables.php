<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->foreignId('fund_source_company_id')
                ->nullable()
                ->after('approved_fund')
                ->constrained('companies')
                ->nullOnDelete();
            $table->foreignId('fund_source_bank_account_id')
                ->nullable()
                ->after('fund_source_company_id')
                ->constrained('company_bank_accounts')
                ->nullOnDelete();
        });

        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->foreignId('fund_source_company_id')
                ->nullable()
                ->after('amount')
                ->constrained('companies')
                ->nullOnDelete();
            $table->foreignId('fund_source_bank_account_id')
                ->nullable()
                ->after('fund_source_company_id')
                ->constrained('company_bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fund_source_bank_account_id');
            $table->dropConstrainedForeignId('fund_source_company_id');
        });

        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fund_source_bank_account_id');
            $table->dropConstrainedForeignId('fund_source_company_id');
        });
    }
};

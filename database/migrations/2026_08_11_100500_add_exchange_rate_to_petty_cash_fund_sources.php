<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('petty_cash_boxes', 'fund_source_exchange_rate')) {
            Schema::table('petty_cash_boxes', function (Blueprint $table) {
                $table->decimal('fund_source_exchange_rate', 18, 6)->nullable()->after('fund_source_bank_account_id');
            });
        }
        if (! Schema::hasColumn('petty_cash_replenishments', 'fund_source_exchange_rate')) {
            Schema::table('petty_cash_replenishments', function (Blueprint $table) {
                $table->decimal('fund_source_exchange_rate', 18, 6)->nullable()->after('fund_source_bank_account_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('petty_cash_replenishments', 'fund_source_exchange_rate')) {
            Schema::table('petty_cash_replenishments', fn (Blueprint $table) => $table->dropColumn('fund_source_exchange_rate'));
        }
        if (Schema::hasColumn('petty_cash_boxes', 'fund_source_exchange_rate')) {
            Schema::table('petty_cash_boxes', fn (Blueprint $table) => $table->dropColumn('fund_source_exchange_rate'));
        }
    }
};

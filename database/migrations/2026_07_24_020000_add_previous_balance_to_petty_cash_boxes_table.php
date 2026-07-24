<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->decimal('previous_balance', 14, 2)->default(0)->after('approved_fund');
            $table->foreignId('previous_petty_cash_id')->nullable()->after('previous_balance')
                ->constrained('petty_cash_boxes')->nullOnDelete();
            $table->unique('previous_petty_cash_id');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->dropUnique(['previous_petty_cash_id']);
            $table->dropConstrainedForeignId('previous_petty_cash_id');
            $table->dropColumn('previous_balance');
        });
    }
};

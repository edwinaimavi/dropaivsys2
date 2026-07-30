<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_expense_observations', function (Blueprint $table) {
            $table->text('correction_comment')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_expense_observations', function (Blueprint $table) {
            $table->dropColumn('correction_comment');
        });
    }
};

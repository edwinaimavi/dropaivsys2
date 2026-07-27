<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_approved_amounts', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('amount');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_approved_amounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('approved_at');
        });
    }
};

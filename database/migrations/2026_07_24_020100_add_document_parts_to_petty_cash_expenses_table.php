<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_expenses', function (Blueprint $table) {
            $table->string('document_series', 20)->nullable()->after('document_type');
            $table->string('document_correlative', 50)->nullable()->after('document_series');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_expenses', function (Blueprint $table) {
            $table->dropColumn(['document_series', 'document_correlative']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->timestamp('stock_moved_at')->nullable()->after('voided_reason');
            $table->timestamp('stock_reversed_at')->nullable()->after('stock_moved_at');
            $table->index('stock_moved_at');
        });
    }

    public function down(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->dropIndex(['stock_moved_at']);
            $table->dropColumn(['stock_moved_at', 'stock_reversed_at']);
        });
    }
};

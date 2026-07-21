<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('customer_branch_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customer_branches')
                ->nullOnDelete();
            $table->text('additional_observations')->nullable()->after('observations');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['customer_branch_id']);
            $table->dropColumn(['customer_branch_id', 'additional_observations']);
        });
    }
};

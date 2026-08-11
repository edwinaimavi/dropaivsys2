<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'opening_balance' => fn (Blueprint $table) => $table->decimal('opening_balance', 18, 4)->default(0)->after('observation'),
            'current_balance' => fn (Blueprint $table) => $table->decimal('current_balance', 18, 4)->default(0)->after('opening_balance'),
            'opening_balance_date' => fn (Blueprint $table) => $table->date('opening_balance_date')->nullable()->after('current_balance'),
            'opening_balance_observation' => fn (Blueprint $table) => $table->text('opening_balance_observation')->nullable()->after('opening_balance_date'),
            'opening_balance_set_by' => fn (Blueprint $table) => $table->foreignId('opening_balance_set_by')->nullable()->after('opening_balance_observation')->constrained('users')->nullOnDelete(),
            'opening_balance_set_at' => fn (Blueprint $table) => $table->timestamp('opening_balance_set_at')->nullable()->after('opening_balance_set_by'),
            'last_movement_at' => fn (Blueprint $table) => $table->timestamp('last_movement_at')->nullable()->after('opening_balance_set_at'),
        ];
        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('company_bank_accounts', $column)) {
                Schema::table('company_bank_accounts', $definition);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_bank_accounts', 'opening_balance_set_by')) {
            Schema::table('company_bank_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('opening_balance_set_by'));
        }
        foreach (['opening_balance', 'current_balance', 'opening_balance_date', 'opening_balance_observation', 'opening_balance_set_at', 'last_movement_at'] as $column) {
            if (Schema::hasColumn('company_bank_accounts', $column)) {
                Schema::table('company_bank_accounts', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};

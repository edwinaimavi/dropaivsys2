<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_entry_expenses', 'shipping_agency_id')) {
                $table->foreignId('shipping_agency_id')
                    ->nullable()
                    ->after('expense_type')
                    ->constrained('shipping_agencies')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_entry_expenses', 'shipping_agency_id')) {
                $table->dropConstrainedForeignId('shipping_agency_id');
            }
        });
    }
};

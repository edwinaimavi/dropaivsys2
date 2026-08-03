<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_entry_expenses', 'expense_category')) {
                $table->string('expense_category', 40)->nullable()->after('supplier_purchase_order_id');
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'cost_origin')) {
                $table->string('cost_origin', 50)->nullable()->after('expense_category');
            }
        });

        DB::table('warehouse_entry_expenses')->whereNull('expense_category')->update([
            'expense_category' => DB::raw("CASE WHEN expense_type IN ('flete', 'transporte', 'movilidad') THEN 'freight_transport' ELSE 'other_expense' END"),
        ]);
        DB::table('warehouse_entry_expenses')->whereNull('cost_origin')->update(['cost_origin' => 'third_party']);
    }

    public function down(): void
    {
        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouse_entry_expenses', 'cost_origin')) $table->dropColumn('cost_origin');
            if (Schema::hasColumn('warehouse_entry_expenses', 'expense_category')) $table->dropColumn('expense_category');
        });
    }
};

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
            $table->boolean('affects_igv')->default(false)->after('amount');
            $table->decimal('igv_rate', 5, 2)->nullable()->after('affects_igv');
            $table->decimal('taxable_amount', 15, 2)->default(0)->after('igv_rate');
            $table->decimal('igv_amount', 15, 2)->default(0)->after('taxable_amount');
            $table->decimal('total_amount', 15, 2)->default(0)->after('igv_amount');
        });

        // El historial no indicaba si el importe incluia IGV. Se conserva completo
        // como no afecto hasta que el usuario lo confirme al editar el ingreso.
        DB::table('warehouse_entry_expenses')->update([
            'affects_igv' => false,
            'igv_rate' => 0,
            'taxable_amount' => DB::raw('amount'),
            'igv_amount' => 0,
            'total_amount' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            $table->dropColumn([
                'affects_igv',
                'igv_rate',
                'taxable_amount',
                'igv_amount',
                'total_amount',
            ]);
        });
    }
};

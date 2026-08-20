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
            $table->boolean('applies_detraction')->default(false)->after('total_amount');
            $table->unsignedBigInteger('detraction_type_id')->nullable()->after('applies_detraction');
            $table->decimal('detraction_percentage', 8, 4)->default(0)->after('detraction_type_id');
            $table->decimal('detraction_amount', 15, 2)->default(0)->after('detraction_percentage');
            $table->decimal('supplier_net_amount', 15, 2)->nullable()->after('detraction_amount');

            $table->foreign('detraction_type_id', 'we_exp_det_type_fk')
                ->references('id')->on('detraction_types')->restrictOnDelete();
        });

        DB::table('warehouse_entry_expenses')->update([
            'supplier_net_amount' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            $table->dropForeign('we_exp_det_type_fk');
            $table->dropColumn([
                'applies_detraction',
                'detraction_type_id',
                'detraction_percentage',
                'detraction_amount',
                'supplier_net_amount',
            ]);
        });
    }
};

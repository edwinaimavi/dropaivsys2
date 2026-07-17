<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('warehouse_entry_id')
                ->constrained('warehouses')->nullOnDelete();
        });

        DB::table('electronic_invoices')
            ->whereNull('warehouse_id')
            ->whereNotNull('warehouse_entry_id')
            ->orderBy('id')
            ->each(function ($invoice) {
                $warehouseId = DB::table('warehouse_entries')
                    ->where('id', $invoice->warehouse_entry_id)
                    ->value('warehouse_id');

                if ($warehouseId) {
                    DB::table('electronic_invoices')
                        ->where('id', $invoice->id)
                        ->update(['warehouse_id' => $warehouseId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};

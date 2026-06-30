<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('supplier_purchase_orders')
            ->where('status', 'draft')
            ->update(['status' => 'registered']);

        DB::statement(
            "ALTER TABLE supplier_purchase_orders MODIFY status VARCHAR(30) NOT NULL DEFAULT 'registered'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE supplier_purchase_orders MODIFY status VARCHAR(30) NOT NULL DEFAULT 'draft'"
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_purchase_orders')
            ->where('status', 'draft')
            ->update(['status' => 'registered']);

        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->string('status', 30)->default('registered')->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });
    }
};

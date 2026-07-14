<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customer_purchase_orders', 'delivery_days')) {
            Schema::table('customer_purchase_orders', function (Blueprint $table) {
                $table->unsignedInteger('delivery_days')
                    ->nullable()
                    ->after('delivery_start_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_purchase_orders', 'delivery_days')) {
            Schema::table('customer_purchase_orders', function (Blueprint $table) {
                $table->dropColumn('delivery_days');
            });
        }
    }
};

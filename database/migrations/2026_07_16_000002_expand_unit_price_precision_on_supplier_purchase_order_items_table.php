<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 6)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->default(0)->change();
        });
    }
};

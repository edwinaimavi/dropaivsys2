<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->decimal('subtotal_exonerated', 20, 10)->default(0)->change();
            $table->decimal('subtotal_taxed', 20, 10)->default(0)->change();
            $table->decimal('igv', 20, 10)->default(0)->change();
            $table->decimal('grand_total', 20, 10)->default(0)->change();
        });

        Schema::table('customer_purchase_order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 20, 10)->default(0)->change();
            $table->decimal('subtotal', 20, 10)->default(0)->change();
            $table->decimal('tax_amount', 20, 10)->default(0)->change();
            $table->decimal('line_total', 20, 10)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->decimal('subtotal_exonerated', 15, 2)->default(0)->change();
            $table->decimal('subtotal_taxed', 15, 2)->default(0)->change();
            $table->decimal('igv', 15, 2)->default(0)->change();
            $table->decimal('grand_total', 15, 2)->default(0)->change();
        });

        Schema::table('customer_purchase_order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->default(0)->change();
            $table->decimal('subtotal', 15, 2)->default(0)->change();
            $table->decimal('tax_amount', 15, 2)->default(0)->change();
            $table->decimal('line_total', 15, 2)->default(0)->change();
        });
    }
};

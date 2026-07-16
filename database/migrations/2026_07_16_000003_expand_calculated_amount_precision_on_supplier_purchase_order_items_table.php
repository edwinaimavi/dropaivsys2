<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->decimal('subtotal', 18, 6)->default(0)->change();
            $table->decimal('tax_amount', 18, 6)->default(0)->change();
            $table->decimal('line_total', 18, 6)->default(0)->change();
            $table->decimal('total_with_igv', 18, 6)->nullable()->change();
            $table->decimal('taxable_base', 18, 6)->nullable()->change();
            $table->decimal('igv_amount', 18, 6)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0)->change();
            $table->decimal('tax_amount', 15, 2)->default(0)->change();
            $table->decimal('line_total', 15, 2)->default(0)->change();
            $table->decimal('total_with_igv', 15, 2)->nullable()->change();
            $table->decimal('taxable_base', 15, 2)->nullable()->change();
            $table->decimal('igv_amount', 15, 2)->nullable()->change();
        });
    }
};

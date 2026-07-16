<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->decimal('total_with_igv', 15, 2)->nullable()->after('line_total');
            $table->decimal('taxable_base', 15, 2)->nullable()->after('total_with_igv');
            $table->decimal('igv_percent', 5, 2)->default(18.00)->after('taxable_base');
            $table->decimal('igv_amount', 15, 2)->nullable()->after('igv_percent');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['total_with_igv', 'taxable_base', 'igv_percent', 'igv_amount']);
        });
    }
};

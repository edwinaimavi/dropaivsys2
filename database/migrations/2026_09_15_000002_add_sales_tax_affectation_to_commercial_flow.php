<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('sales_tax_affectation_code', 2)
                ->nullable()
                ->after('is_taxable');
            $table->index('sales_tax_affectation_code', 'articles_sales_tax_affectation_idx');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->string('tax_affectation_code', 2)
                ->nullable()
                ->after('line_total');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('subtotal_unaffected', 15, 2)
                ->default(0)
                ->after('subtotal_exonerated');
        });

        Schema::table('customer_purchase_order_items', function (Blueprint $table) {
            $table->string('tax_affectation_code', 2)
                ->nullable()
                ->after('tax_amount');
        });

        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->decimal('subtotal_unaffected', 20, 10)
                ->default(0)
                ->after('subtotal_exonerated');
        });
    }

    public function down(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('subtotal_unaffected');
        });

        Schema::table('customer_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('tax_affectation_code');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('subtotal_unaffected');
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('tax_affectation_code');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_sales_tax_affectation_idx');
            $table->dropColumn('sales_tax_affectation_code');
        });
    }
};

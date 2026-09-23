<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->string('sunat_document_type_code', 2)->nullable()->after('document_type');
            $table->dateTime('movement_date')->nullable()->after('document_date');
            $table->decimal('exchange_rate', 18, 6)->nullable()->after('currency_id');
            $table->index(
                ['company_id', 'supplier_id', 'sunat_document_type_code', 'document_series', 'document_number'],
                'we_supplier_document_lookup_idx'
            );
        });

        Schema::table('warehouse_entry_items', function (Blueprint $table) {
            $table->boolean('igv_recoverable')->nullable()->after('is_free');
            $table->decimal('acquisition_cost_base', 18, 2)->nullable()->after('igv_recoverable');
            $table->decimal('acquisition_unit_cost_base', 18, 6)->nullable()->after('acquisition_cost_base');
        });

        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            $table->boolean('igv_recoverable')->nullable()->after('affects_igv');
            $table->decimal('exchange_rate', 18, 6)->nullable()->after('currency_id');
        });

        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->string('tax_affectation_code', 2)->nullable()->after('line_total');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_affectation_code');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('tax_rate');
            $table->boolean('is_free')->default(false)->after('igv_amount');
            $table->boolean('igv_recoverable')->nullable()->after('is_free');
            $table->index('tax_affectation_code', 'spoi_tax_affectation_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->dropIndex('spoi_tax_affectation_code_idx');
            $table->dropColumn([
                'tax_affectation_code',
                'tax_rate',
                'discount_amount',
                'is_free',
                'igv_recoverable',
            ]);
        });

        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            $table->dropColumn(['igv_recoverable', 'exchange_rate']);
        });

        Schema::table('warehouse_entry_items', function (Blueprint $table) {
            $table->dropColumn([
                'igv_recoverable',
                'acquisition_cost_base',
                'acquisition_unit_cost_base',
            ]);
        });

        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->dropIndex('we_supplier_document_lookup_idx');
            $table->dropColumn(['sunat_document_type_code', 'movement_date', 'exchange_rate']);
        });
    }
};

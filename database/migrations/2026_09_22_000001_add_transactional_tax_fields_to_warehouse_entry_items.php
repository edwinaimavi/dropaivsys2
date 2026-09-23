<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entry_items', function (Blueprint $table) {
            $table->string('tax_affectation_code', 2)->nullable()->after('line_total');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_affectation_code');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('tax_rate');
            $table->decimal('taxable_base', 15, 2)->nullable()->after('discount_amount');
            $table->boolean('is_free')->default(false)->after('taxable_base');

            $table->index('tax_affectation_code', 'wei_tax_affectation_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_entry_items', function (Blueprint $table) {
            $table->dropIndex('wei_tax_affectation_code_idx');
            $table->dropColumn([
                'tax_affectation_code',
                'tax_rate',
                'discount_amount',
                'taxable_base',
                'is_free',
            ]);
        });
    }
};

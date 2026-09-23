<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->string('sunat_establishment_code_snapshot', 20)->nullable()->after('warehouse_id');
            $table->string('article_code_snapshot', 100)->nullable()->after('article_id');
            $table->string('article_description_snapshot')->nullable()->after('article_code_snapshot');
            $table->string('sunat_existence_type_code_snapshot', 20)->nullable()->after('article_description_snapshot');
            $table->string('existence_catalog_code_snapshot', 50)->nullable()->after('sunat_existence_type_code_snapshot');
            $table->string('existence_code_snapshot', 24)->nullable()->after('existence_catalog_code_snapshot');
            $table->string('sunat_unit_code_snapshot', 20)->nullable()->after('unit_id');
            $table->string('unit_description_snapshot')->nullable()->after('sunat_unit_code_snapshot');
            $table->string('valuation_method_code_snapshot', 20)->nullable()->after('unit_description_snapshot');
            $table->string('valuation_method_description_snapshot')->nullable()->after('valuation_method_code_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->dropColumn([
                'sunat_establishment_code_snapshot',
                'article_code_snapshot',
                'article_description_snapshot',
                'sunat_existence_type_code_snapshot',
                'existence_catalog_code_snapshot',
                'existence_code_snapshot',
                'sunat_unit_code_snapshot',
                'unit_description_snapshot',
                'valuation_method_code_snapshot',
                'valuation_method_description_snapshot',
            ]);
        });
    }
};

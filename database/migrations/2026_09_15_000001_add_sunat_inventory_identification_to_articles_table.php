<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('sunat_inventory_catalog_item_id')
                ->nullable()
                ->after('sunat_existence_type_item_id')
                ->constrained('sunat_catalog_items')
                ->restrictOnDelete();
            $table->string('sunat_inventory_catalog_code', 24)
                ->nullable()
                ->after('sunat_inventory_catalog_item_id');
            $table->foreignId('sunat_standard_catalog_item_id')
                ->nullable()
                ->after('sunat_inventory_catalog_code')
                ->constrained('sunat_catalog_items')
                ->restrictOnDelete();
            $table->string('sunat_standard_code', 128)
                ->nullable()
                ->after('sunat_standard_catalog_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sunat_standard_catalog_item_id');
            $table->dropConstrainedForeignId('sunat_inventory_catalog_item_id');
            $table->dropColumn([
                'sunat_inventory_catalog_code',
                'sunat_standard_code',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('sunat_existence_type_item_id')
                ->nullable()
                ->after('is_inventory_item')
                ->constrained('sunat_catalog_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sunat_existence_type_item_id');
        });
    }
};

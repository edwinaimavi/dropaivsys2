<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('item_kind', 20)->nullable()->after('billing_name');
            $table->boolean('is_inventory_item')->nullable()->after('item_kind');
            $table->index(['item_kind', 'is_inventory_item'], 'articles_inventory_classification_idx');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_inventory_classification_idx');
            $table->dropColumn(['item_kind', 'is_inventory_item']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('sunat_establishment_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'warehouse_id'], 'cw_company_warehouse_unique');
            $table->index(['warehouse_id', 'is_active'], 'cw_warehouse_active_idx');
            $table->index(['company_id', 'is_active'], 'cw_company_active_idx');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('stock_key')
                ->constrained('companies')->restrictOnDelete();
            $table->index(
                ['company_id', 'warehouse_id', 'article_id'],
                'ws_company_warehouse_article_idx'
            );
        });

        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('movement_number')
                ->constrained('companies')->restrictOnDelete();
            $table->index(
                ['company_id', 'warehouse_id', 'movement_date'],
                'wkm_company_warehouse_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->dropIndex('wkm_company_warehouse_date_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->dropIndex('ws_company_warehouse_article_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::dropIfExists('company_warehouses');
    }
};

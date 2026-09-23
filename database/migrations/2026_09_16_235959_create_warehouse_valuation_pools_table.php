<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_valuation_pools', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('article_id')
                ->constrained('articles')
                ->restrictOnDelete();

            $table->decimal('current_quantity', 18, 4)->default(0);
            $table->decimal('average_unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 20, 2)->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['company_id', 'warehouse_id', 'article_id'],
                'wvp_company_warehouse_article_unique'
            );

            $table->index(
                ['warehouse_id', 'article_id'],
                'wvp_warehouse_article_idx'
            );

            $table->index(
                ['company_id', 'article_id'],
                'wvp_company_article_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_valuation_pools');
    }
};

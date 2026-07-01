<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_kardex_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number', 30)->unique();
            $table->foreignId('warehouse_stock_id')->nullable()->constrained('warehouse_stocks')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained('presentations')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('origin', 100)->nullable();
            $table->string('cost_type', 100)->nullable();
            $table->dateTime('movement_date');
            $table->string('movement_type', 40);
            $table->string('operation_type', 60);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_item_type')->nullable();
            $table->unsignedBigInteger('source_item_id')->nullable();
            $table->string('document_type', 100)->nullable();
            $table->string('document_series', 20)->nullable();
            $table->string('document_number', 50)->nullable();
            $table->string('related_party_type', 40)->nullable();
            $table->unsignedBigInteger('related_party_id')->nullable();
            $table->string('related_party_name')->nullable();
            $table->decimal('quantity_in', 15, 4)->default(0);
            $table->decimal('quantity_out', 15, 4)->default(0);
            $table->decimal('balance_quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 6)->default(0);
            $table->decimal('total_cost_in', 15, 2)->default(0);
            $table->decimal('total_cost_out', 15, 2)->default(0);
            $table->decimal('average_unit_cost', 15, 6)->default(0);
            $table->decimal('balance_total_cost', 15, 2)->default(0);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->text('observations')->nullable();
            $table->string('status', 30)->default('registered');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('warehouse_id');
            $table->index('article_id');
            $table->index('warehouse_stock_id');
            $table->index(['source_type', 'source_id'], 'wkm_source_idx');
            $table->index(['source_item_type', 'source_item_id'], 'wkm_source_item_idx');
            $table->index('movement_date');
            $table->index('movement_type');
            $table->index('operation_type');
            $table->index('status');
            $table->index('lot_number');
            $table->index('expiration_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_kardex_movements');
    }
};

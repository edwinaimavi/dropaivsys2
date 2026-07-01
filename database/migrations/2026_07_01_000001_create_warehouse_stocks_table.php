<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('stock_key')->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained('presentations')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('origin', 100)->nullable();
            $table->string('cost_type', 100)->nullable();
            $table->decimal('current_quantity', 15, 4)->default(0);
            $table->decimal('reserved_quantity', 15, 4)->default(0);
            $table->decimal('average_unit_cost', 15, 6)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('min_stock', 15, 4)->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('warehouse_id');
            $table->index('article_id');
            $table->index('lot_number');
            $table->index('expiration_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};

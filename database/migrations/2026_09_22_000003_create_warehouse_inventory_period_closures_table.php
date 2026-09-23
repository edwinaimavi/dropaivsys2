<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_inventory_period_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('action', 20);
            $table->text('reason')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['company_id', 'warehouse_id', 'year', 'month', 'id'],
                'wipc_period_event_idx'
            );
            $table->index(['company_id', 'year', 'month'], 'wipc_company_period_idx');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_inventory_period_closures');
    }
};

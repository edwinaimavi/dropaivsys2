<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->foreign('warehouse_id', 'warehouse_entries_warehouse_id_fk')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_entries', function (Blueprint $table) {
            $table->dropForeign('warehouse_entries_warehouse_id_fk');
        });

        Schema::dropIfExists('warehouses');
    }
};

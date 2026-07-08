<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_agency_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_agency_id')->constrained('shipping_agencies')->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('branch_name');
            $table->string('address');
            $table->foreignId('ubigeo_id')->nullable()->constrained('ubigeos')->nullOnDelete();
            $table->string('department')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('reference')->nullable();
            $table->boolean('is_main')->default(false);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shipping_agency_id', 'status']);
            $table->index('is_main');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_agency_branches');
    }
};

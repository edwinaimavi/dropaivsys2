<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('ruc', 20)->nullable();
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('agency_type', 30);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('agency_type');
            $table->unique(['ruc', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_agencies');
    }
};

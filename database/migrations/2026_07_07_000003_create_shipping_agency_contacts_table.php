<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_agency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_agency_id')->constrained('shipping_agencies')->cascadeOnDelete();
            $table->foreignId('shipping_agency_branch_id')->nullable()->constrained('shipping_agency_branches')->nullOnDelete();
            $table->string('contact_name');
            $table->string('position')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shipping_agency_id', 'status']);
            $table->index(['shipping_agency_branch_id', 'status']);
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_agency_contacts');
    }
};

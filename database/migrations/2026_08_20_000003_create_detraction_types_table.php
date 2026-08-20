<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detraction_types', function (Blueprint $table) {
            $table->id();
            $table->string('appendix', 20)->nullable();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('percentage', 8, 4);
            $table->string('legal_reference')->nullable();
            $table->date('effective_from')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['appendix', 'code'], 'det_types_app_code_uq');
            $table->index(['status', 'appendix'], 'det_types_status_app_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detraction_types');
    }
};

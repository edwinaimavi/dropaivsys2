<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ubigeos', function (Blueprint $table) {

            $table->id();

            // Código oficial INEI
            $table->string('code', 6)->unique();

            // Ubicación
            $table->string('department', 100);
            $table->string('province', 100);
            $table->string('district', 100);

            // Nombre completo para búsquedas rápidas
            $table->string('full_name', 255);

            // Estado
            $table->enum('status', ['ACTIVE', 'INACTIVE'])
                ->default('ACTIVE');

            // Auditoría
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('code');
            $table->index('department');
            $table->index('province');
            $table->index('district');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubigeos');
    }
};

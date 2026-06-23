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
        Schema::create('categories', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | DATOS PRINCIPALES
            |--------------------------------------------------------------------------
            */

            // Nombre de la categoría
            $table->string('description', 255);

            // Código interno
            $table->string('code', 20)->unique();

            // Tipo
            // Ejemplo:
            // PRODUCTO COMERCIAL
            // SERVICIO
            // INSUMO
            $table->string('type', 100);

            // Observaciones internas
            $table->text('observation')->nullable();

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'ACTIVE',
                'INACTIVE',
            ])->default('ACTIVE');

            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */

            // Usuario creador
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Último usuario que editó
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Usuario que eliminó
            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | FECHAS
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            // Eliminación lógica
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
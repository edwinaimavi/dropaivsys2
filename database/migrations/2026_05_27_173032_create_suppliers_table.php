
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
        Schema::create('suppliers', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | DATOS PRINCIPALES
            |--------------------------------------------------------------------------
            */

            $table->string('ruc', 11)->unique();

            $table->string('business_name');

            $table->string('short_name')->nullable();

            $table->string('address')->nullable();

            /*
            |--------------------------------------------------------------------------
            | UBIGEO
            |--------------------------------------------------------------------------
            */

            // Preparado para futura tabla ubigeos
            $table->foreignId('ubigeo_id')
                ->nullable()
                ->constrained('ubigeos')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CONFIGURACIÓN
            |--------------------------------------------------------------------------
            */

            $table->string('supplier_type', 100);

            $table->string('payment_condition', 100);

            /*
            |--------------------------------------------------------------------------
            | CONTACTO
            |--------------------------------------------------------------------------
            */

            $table->string('contact_name')->nullable();

            $table->string('email')->nullable();

            $table->string('phone', 20)->nullable();

            /*
            |--------------------------------------------------------------------------
            | IMPUESTOS
            |--------------------------------------------------------------------------
            */

            $table->decimal('igv_percentage', 5, 2)
                ->default(18.00);

            /*
            |--------------------------------------------------------------------------
            | OBSERVACIONES
            |--------------------------------------------------------------------------
            */

            $table->text('observation')->nullable();

            /*
            |--------------------------------------------------------------------------
            | ESTADO
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'ACTIVE',
                'INACTIVE'
            ])->default('ACTIVE');

            /*
            |--------------------------------------------------------------------------
            | AUDITORÍA
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};

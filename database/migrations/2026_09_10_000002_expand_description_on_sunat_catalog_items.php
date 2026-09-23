<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunat_catalog_items', function (Blueprint $table) {
            $table->text('description')->change();
        });
    }

    /**
     * No se reduce nuevamente a VARCHAR(255), porque hacerlo después de cargar
     * descripciones oficiales extensas podría truncar o perder información.
     */
    public function down(): void
    {
        // Reversión intencionalmente no destructiva.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_study_quote_items', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR SNAPSHOTS ACTUALES
            |--------------------------------------------------------------------------
            */

            $table->dropColumn([
                'brand_snapshot',
                'unit_snapshot',
                'presentation_snapshot',
            ]);

            /*
            |--------------------------------------------------------------------------
            | NUEVAS RELACIONES
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('brand_id')
                ->nullable()
                ->after('article_id');

            $table->unsignedBigInteger('unit_id')
                ->nullable()
                ->after('brand_id');

            $table->unsignedBigInteger('presentation_id')
                ->nullable()
                ->after('unit_id');

            /*
            |--------------------------------------------------------------------------
            | TOTALES
            |--------------------------------------------------------------------------
            */

            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->after('unit_price');

            $table->decimal('tax_amount', 15, 2)
                ->default(0)
                ->after('subtotal');

            $table->decimal('total', 15, 2)
                ->default(0)
                ->after('tax_amount');

            /*
            |--------------------------------------------------------------------------
            | OBSERVACIÓN
            |--------------------------------------------------------------------------
            */

            $table->text('observation')
                ->nullable()
                ->after('total');

            /*
            |--------------------------------------------------------------------------
            | FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            $table->foreign('brand_id')
                ->references('id')
                ->on('brands');

            $table->foreign('unit_id')
                ->references('id')
                ->on('units');

            $table->foreign('presentation_id')
                ->references('id')
                ->on('presentations');
        });
    }

    public function down(): void
    {
        Schema::table('market_study_quote_items', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR FOREIGN KEYS
            |--------------------------------------------------------------------------
            */

            $table->dropForeign(['brand_id']);
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['presentation_id']);

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR NUEVOS CAMPOS
            |--------------------------------------------------------------------------
            */

            $table->dropColumn([
                'brand_id',
                'unit_id',
                'presentation_id',
                'subtotal',
                'tax_amount',
                'total',
                'observation'
            ]);

            /*
            |--------------------------------------------------------------------------
            | RESTAURAR SNAPSHOTS
            |--------------------------------------------------------------------------
            */

            $table->string('brand_snapshot')
                ->nullable();

            $table->string('unit_snapshot')
                ->nullable();

            $table->string('presentation_snapshot')
                ->nullable();
        });
    }
};

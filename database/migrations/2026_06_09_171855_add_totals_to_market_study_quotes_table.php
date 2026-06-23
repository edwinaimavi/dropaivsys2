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
        Schema::table('market_study_quotes', function (Blueprint $table) {

            $table->decimal('gravada', 15, 3)
                ->default(0)
                ->after('commercial_conditions');

            $table->decimal('exonerada', 15, 3)
                ->default(0)
                ->after('gravada');

            $table->decimal('inafecta', 15, 3)
                ->default(0)
                ->after('exonerada');

            $table->decimal('igv', 15, 3)
                ->default(0)
                ->after('inafecta');

            $table->decimal('grand_total', 15, 3)
                ->default(0)
                ->after('igv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_study_quotes', function (Blueprint $table) {

            $table->dropColumn([
                'gravada',
                'exonerada',
                'inafecta',
                'igv',
                'grand_total'
            ]);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketStudyItemWinnersTable extends Migration
{
    public function up()
    {
        Schema::create('market_study_item_winners', function (Blueprint $table) {
            $table->id();

            // Ítem original del estudio de mercado
            $table->foreignId('market_study_item_id')
                ->constrained('market_study_items')
                ->cascadeOnDelete();

            // Ítem específico de la cotización ganadora
            $table->foreignId('market_study_quote_item_id')
                ->constrained('market_study_quote_items')
                ->cascadeOnDelete();

            $table->timestamps();

            // Un ítem del estudio solo puede tener un ganador
            $table->unique('market_study_item_id', 'uq_market_study_item_winner');
        });
    }

    public function down()
    {
        Schema::dropIfExists('market_study_item_winners');
    }
}

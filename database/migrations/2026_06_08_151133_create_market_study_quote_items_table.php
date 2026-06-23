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
        Schema::create('market_study_quote_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('market_study_quote_id')
                ->constrained('market_study_quotes')
                ->cascadeOnDelete();

            $table->foreignId('market_study_item_id')
                ->constrained('market_study_items')
                ->cascadeOnDelete();

            $table->foreignId('article_id')
                ->constrained('articles');

            $table->string('brand_snapshot', 255)
                ->nullable();

            $table->string('unit_snapshot', 255)
                ->nullable();

            $table->string('presentation_snapshot', 255)
                ->nullable();

            $table->date('manufacture_date')
                ->nullable();

            $table->date('expiration_date')
                ->nullable();

            $table->string('origin', 255)
                ->nullable();

            $table->string('sanitary_registration', 255)
                ->nullable();

            $table->string('tax_type', 50)
                ->nullable();

            $table->decimal('quantity', 12, 2)
                ->default(0);

            $table->decimal('unit_price', 12, 4)
                ->default(0);

            $table->boolean('status')
                ->default(true);

            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->timestamps();

            $table->index('market_study_quote_id');
            $table->index('market_study_item_id');
            $table->index('article_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_study_quote_items');
    }
};

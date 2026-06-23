<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_study_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('market_study_id')
                ->constrained('market_studies')
                ->cascadeOnDelete();

            $table->foreignId('article_id')
                ->constrained('articles');

            $table->string('article_code_snapshot', 100);

            $table->string('billing_name_snapshot');

            $table->string('category_snapshot')->nullable();
            $table->string('subcategory_snapshot')->nullable();

            $table->string('presentation_snapshot')->nullable();

            $table->string('weight_snapshot', 100)->nullable();

            $table->string('cost_condition_snapshot', 100)->nullable();

            $table->boolean('status')->default(1);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_study_items');
    }
};

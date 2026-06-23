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
        Schema::create('market_study_quotes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('market_study_id')
                ->constrained('market_studies');

            $table->string('quote_number', 100)->nullable();

            $table->foreignId('supplier_id')
                ->constrained('suppliers');

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained('currencies');

            $table->decimal('exchange_rate', 12, 4)
                ->nullable();

            $table->string('payment_condition', 150)
                ->nullable();

            $table->decimal('shipping_cost', 12, 2)
                ->default(0);

            $table->decimal('other_costs', 12, 2)
                ->default(0);

            $table->date('delivery_date')
                ->nullable();

            $table->text('commercial_conditions')
                ->nullable();

            $table->boolean('status')
                ->default(true);

            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index('market_study_id');
            $table->index('supplier_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_study_quotes');
    }
};

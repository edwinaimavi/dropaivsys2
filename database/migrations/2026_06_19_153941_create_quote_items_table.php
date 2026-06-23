
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('quote_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('market_study_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('article_id')
                ->constrained();

            $table->string('article_code')->nullable();

            $table->string('billing_name_snapshot');

            $table->text('note')->nullable();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('presentation_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('origin', 100)->nullable();

            $table->date('expiration_date')->nullable();

            $table->string('cost_type')
                ->default('PESO');

            $table->decimal('cost_price', 15, 2)
                ->default(0);

            $table->decimal('quantity', 15, 2)
                ->default(0);

            $table->decimal('unit_price', 15, 2)
                ->default(0);

            $table->decimal('discount_percentage', 8, 2)
                ->default(0);

            $table->decimal('discount_amount', 15, 2)
                ->default(0);

            $table->decimal('line_total', 15, 2)
                ->default(0);

            $table->boolean('is_winner')
                ->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};

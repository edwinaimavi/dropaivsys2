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
        Schema::create('articles', function (Blueprint $table) {

            $table->id();

            $table->string('code', 20)->unique();

            $table->foreignId('category_id')
                ->constrained('categories');

            $table->foreignId('subcategory_id')
                ->nullable()
                ->constrained('subcategories');

            $table->foreignId('presentation_id')
                ->nullable()
                ->constrained('presentations');

            $table->foreignId('unit_id')
                ->constrained('units');

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands');

            $table->string('legal_name');
            $table->string('commercial_name')->nullable();
            $table->string('billing_name');

            $table->boolean('is_taxable')
                ->default(true);

            $table->decimal('minimum_stock', 12, 2)
                ->default(0);

            $table->boolean('has_batch')
                ->default(false);

            $table->boolean('has_expiration')
                ->default(false);

            $table->text('observation')
                ->nullable();

            $table->enum('status', [
                'ACTIVE',
                'INACTIVE'
            ])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->unsignedBigInteger('deleted_by')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            // INDEXES

            $table->index('code');
            $table->index('legal_name');
            $table->index('billing_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};

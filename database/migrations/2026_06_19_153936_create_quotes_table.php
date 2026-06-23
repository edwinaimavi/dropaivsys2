<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {

            $table->id();

            $table->string('quote_number')->unique();

            $table->foreignId('market_study_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('customer_id')
                ->constrained();


            $table->foreignId('company_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('payment_condition')->nullable();

            $table->text('delivery_address')->nullable();

            $table->enum('show_code_type', [
                'internal',
                'customer',
                'both'
            ])->default('both');

            $table->enum('orientation', [
                'vertical',
                'horizontal'
            ])->default('vertical');

            $table->enum('billing_type', [
                'local',
                'export'
            ])->default('local');

            $table->boolean('affect_igv')->default(false);

            $table->date('validity_date')->nullable();

            $table->integer('delivery_days')->nullable();

            $table->string('delivery_time')->nullable();

            $table->longText('observations')->nullable();

            $table->decimal('subtotal_exonerated', 15, 2)->default(0);
            $table->decimal('subtotal_taxed', 15, 2)->default(0);
            $table->decimal('igv', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->enum('status', [
                'draft',
                'sent',
                'approved',
                'rejected',
                'expired',
                'awarded'
            ])->default('draft');

            $table->foreignId('created_by')
                ->constrained('users');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};

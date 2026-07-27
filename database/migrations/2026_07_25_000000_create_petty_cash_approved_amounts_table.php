<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_approved_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->boolean('active')->default(true);
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'currency_id'], 'petty_cash_approved_company_currency_unique');
            $table->index('active');
        });

        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->foreignId('approved_amount_id')->nullable()->after('currency_id')
                ->constrained('petty_cash_approved_amounts')->nullOnDelete();
            $table->decimal('approved_amount_snapshot', 14, 2)->nullable()->after('approved_amount_id');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->dropColumn('approved_amount_snapshot');
            $table->dropConstrainedForeignId('approved_amount_id');
        });

        Schema::dropIfExists('petty_cash_approved_amounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_expense_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_expense_id')
                ->constrained('petty_cash_expenses')
                ->cascadeOnDelete();
            $table->text('observation');
            $table->string('status', 20)->default('OPEN');
            $table->foreignId('observed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('observed_at');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(
                ['petty_cash_expense_id', 'status'],
                'pc_expense_observations_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_expense_observations');
    }
};

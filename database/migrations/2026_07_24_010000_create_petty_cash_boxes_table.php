<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('periodicity', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('approved_fund', 14, 2);
            $table->decimal('opening_amount', 14, 2);
            $table->decimal('total_expenses', 14, 2)->default(0);
            $table->decimal('cash_balance', 14, 2);
            $table->decimal('reimbursement_amount', 14, 2)->default(0);
            $table->string('responsible_name', 255);
            $table->string('responsible_dni', 8);
            $table->string('supervisor_name', 255);
            $table->string('supervisor_dni', 8);
            $table->text('observations')->nullable();
            $table->string('status', 30)->default('OPEN');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reimbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reimbursed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'period_year', 'period_month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_boxes');
    }
};

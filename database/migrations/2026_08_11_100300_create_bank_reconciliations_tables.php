<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('company_bank_account_id')->constrained('company_bank_accounts')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('period', 7);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('system_balance', 18, 4);
            $table->decimal('bank_statement_balance', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->string('status', 20)->default('ABIERTA');
            $table->text('observation')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_bank_account_id', 'period'], 'bank_reconciliations_account_period_idx');
            $table->index(['company_bank_account_id', 'status'], 'bank_reconciliations_account_status_idx');
        });

        Schema::create('bank_reconciliation_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->foreignId('bank_movement_id')->constrained('bank_movements')->restrictOnDelete();
            $table->string('status', 20)->default('CONCILIADO');
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->unique(['bank_reconciliation_id', 'bank_movement_id'], 'bank_reconciliation_movement_unique');
            $table->index(['bank_movement_id', 'status'], 'bank_reconciliation_movement_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_movements');
        Schema::dropIfExists('bank_reconciliations');
    }
};

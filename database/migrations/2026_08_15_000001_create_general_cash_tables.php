<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_cash_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('general_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('general_cash_box_id')->constrained('general_cash_boxes')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('company_bank_account_id')->nullable()->constrained('company_bank_accounts')->restrictOnDelete();
            $table->foreignId('bank_movement_id')->nullable()->unique()->constrained('bank_movements')->restrictOnDelete();
            $table->dateTime('movement_date');
            $table->string('direction', 3);
            $table->string('movement_type', 40);
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 18, 4);
            $table->decimal('previous_balance', 18, 4);
            $table->decimal('new_balance', 18, 4);
            $table->string('operation_number', 100)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_name', 150)->nullable();
            $table->string('description', 255);
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('REGISTERED');
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->foreign('reversal_of_id', 'general_cash_movements_reversal_fk')
                ->references('id')->on('general_cash_movements')->restrictOnDelete();
            $table->index(['general_cash_box_id', 'movement_date'], 'general_cash_movements_box_date_idx');
            $table->index(['source_type', 'source_id'], 'general_cash_movements_source_idx');
        });

        Schema::create('general_cash_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->foreignId('general_cash_box_id')->constrained('general_cash_boxes')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('general_cash_movement_id')->nullable()->unique()->constrained('general_cash_movements')->restrictOnDelete();
            $table->date('expense_date');
            $table->string('expense_type', 80);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('person_name', 180)->nullable();
            $table->string('identity_document', 20)->nullable();
            $table->string('concept', 255);
            $table->string('document_type', 40);
            $table->string('document_series', 30)->nullable();
            $table->string('document_number', 80)->nullable();
            $table->decimal('amount', 18, 4);
            $table->boolean('affects_igv')->default(false);
            $table->decimal('taxable_base', 18, 4)->default(0);
            $table->decimal('igv_amount', 18, 4)->default(0);
            $table->string('expense_classification', 30);
            $table->string('status', 20)->default('REGISTERED');
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('observed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('observed_at')->nullable();
            $table->text('observation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['general_cash_box_id', 'expense_date'], 'general_cash_expenses_box_date_idx');
            $table->index(['general_cash_box_id', 'status'], 'general_cash_expenses_box_status_idx');
        });

        Schema::create('general_cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('general_cash_box_id')->constrained('general_cash_boxes')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->dateTime('reconciliation_date');
            $table->decimal('system_balance', 18, 4);
            $table->decimal('physical_balance', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_name', 150)->nullable();
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('CLOSED');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['general_cash_box_id', 'reconciliation_date'], 'general_cash_reconciliations_box_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_cash_reconciliations');
        Schema::dropIfExists('general_cash_expenses');
        Schema::dropIfExists('general_cash_movements');
        Schema::dropIfExists('general_cash_boxes');
    }
};

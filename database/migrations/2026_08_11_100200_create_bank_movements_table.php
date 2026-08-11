<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_movements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('company_bank_account_id')->constrained('company_bank_accounts')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('bank_transfer_id')->nullable()->constrained('bank_transfers')->restrictOnDelete();
            $table->dateTime('movement_date');
            $table->string('movement_type', 40);
            $table->decimal('amount', 18, 4);
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->decimal('amount_pen', 18, 4);
            $table->string('direction', 3);
            $table->string('concept', 150);
            $table->text('description')->nullable();
            $table->string('operation_number', 100)->nullable();
            $table->string('document_type', 50)->nullable();
            $table->string('document_series', 30)->nullable();
            $table->string('document_number', 80)->nullable();
            $table->date('document_date')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code', 100)->nullable();
            $table->string('source_description')->nullable();
            $table->string('status', 20)->default('REGISTRADO');
            $table->decimal('balance_after', 18, 4);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->foreign('reversal_of_id', 'bank_movements_reversal_of_fk')
                ->references('id')->on('bank_movements')->restrictOnDelete();
            $table->index(['company_bank_account_id', 'movement_date'], 'bank_movements_account_date_idx');
            $table->index(['company_bank_account_id', 'status'], 'bank_movements_account_status_idx');
            $table->index(['source_type', 'source_id'], 'bank_movements_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_movements');
    }
};

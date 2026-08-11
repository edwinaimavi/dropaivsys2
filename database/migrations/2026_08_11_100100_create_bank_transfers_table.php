<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('from_company_bank_account_id')->constrained('company_bank_accounts')->restrictOnDelete();
            $table->foreignId('to_company_bank_account_id')->constrained('company_bank_accounts')->restrictOnDelete();
            $table->dateTime('transfer_date');
            $table->decimal('amount', 18, 4);
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('destination_amount', 18, 4);
            $table->foreignId('destination_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->decimal('amount_pen', 18, 4);
            $table->string('operation_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 20)->default('REGISTRADO');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'transfer_date']);
            $table->index(['from_company_bank_account_id', 'status'], 'bank_transfers_from_status_idx');
            $table->index(['to_company_bank_account_id', 'status'], 'bank_transfers_to_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};

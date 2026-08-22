<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_expense_exchanges', function (Blueprint $table) {
            $table->decimal('original_amount', 14, 2)->nullable()->after('total_amount');
            $table->decimal('supported_amount', 14, 2)->default(0)->after('original_amount');
            $table->decimal('returned_amount', 14, 2)->default(0)->after('supported_amount');
            $table->decimal('pending_amount', 14, 2)->nullable()->after('returned_amount');
            $table->string('settlement_status', 30)->nullable()->after('pending_amount');
            $table->dateTime('settled_at')->nullable()->after('settlement_status');
            $table->index(['petty_cash_box_id', 'settlement_status'], 'pc_exchange_settlement_status_idx');
        });

        Schema::create('petty_cash_expense_exchange_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('petty_cash_expense_exchanges')->cascadeOnDelete();
            $table->unsignedBigInteger('issuer_id')->nullable();
            $table->string('issuer_ruc', 11)->nullable();
            $table->string('issuer_name');
            $table->string('document_type', 30);
            $table->string('series', 20);
            $table->string('number', 50);
            $table->date('issue_date');
            $table->string('concept', 500);
            $table->decimal('amount', 14, 2);
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('issuer_id', 'pc_exchange_doc_issuer_fk')
                ->references('id')->on('document_issuers')->nullOnDelete();
            $table->index(['exchange_id', 'status'], 'pc_exchange_doc_status_idx');
            $table->index(['issuer_ruc', 'document_type', 'series', 'number'], 'pc_exchange_doc_lookup_idx');
        });

        Schema::create('petty_cash_expense_exchange_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('petty_cash_expense_exchanges')->cascadeOnDelete();
            $table->foreignId('petty_cash_box_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('return_date');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_name')->nullable();
            $table->text('observation')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['petty_cash_box_id', 'status'], 'pc_exchange_return_box_idx');
            $table->index(['exchange_id', 'status'], 'pc_exchange_return_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_expense_exchange_returns');
        Schema::dropIfExists('petty_cash_expense_exchange_documents');

        Schema::table('petty_cash_expense_exchanges', function (Blueprint $table) {
            $table->dropIndex('pc_exchange_settlement_status_idx');
            $table->dropColumn([
                'original_amount', 'supported_amount', 'returned_amount',
                'pending_amount', 'settlement_status', 'settled_at',
            ]);
        });
    }
};

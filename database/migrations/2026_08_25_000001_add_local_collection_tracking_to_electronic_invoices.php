<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->decimal('paid_amount', 20, 10)->default(0)->after('total_amount');
            $table->decimal('pending_amount', 20, 10)->default(0)->after('paid_amount');
            $table->string('payment_status', 20)->default('pending')->after('pending_amount');
            $table->index(['payment_status', 'due_date'], 'ei_payment_status_due_idx');
        });

        DB::table('electronic_invoices')
            ->whereNotIn('status', ['draft', 'cancelled', 'voided'])
            ->where('is_voided', false)
            ->update([
                'pending_amount' => DB::raw('total_amount'),
                'payment_status' => 'pending',
            ]);

        Schema::create('invoice_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_id')->constrained('electronic_invoices')->restrictOnDelete();
            $table->foreignId('customer_purchase_order_id')->nullable()->constrained('customer_purchase_orders')->nullOnDelete();
            $table->foreignId('company_bank_account_id')->constrained('company_bank_accounts')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->date('collection_date');
            $table->decimal('amount', 20, 10);
            $table->decimal('invoice_currency_amount', 20, 10);
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->string('operation_number', 100)->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime_type', 100)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('bank_movement_id')->nullable()->constrained('bank_movements')->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['electronic_invoice_id', 'collection_date'], 'invoice_collections_invoice_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_collections');

        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->dropIndex('ei_payment_status_due_idx');
            $table->dropColumn(['paid_amount', 'pending_amount', 'payment_status']);
        });
    }
};

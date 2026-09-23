<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 30)->unique();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_purchase_order_id')->constrained('customer_purchase_orders')->restrictOnDelete();
            $table->foreignId('warehouse_dispatch_id')->constrained('warehouse_dispatches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->dateTime('return_date');
            $table->string('status', 30)->default('draft');
            $table->string('reason', 60);
            $table->text('reason_description')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'cr_company_status_idx');
            $table->index(['customer_purchase_order_id', 'status'], 'cr_order_status_idx');
            $table->index(['warehouse_dispatch_id', 'status'], 'cr_dispatch_status_idx');
            $table->index(['warehouse_id', 'return_date'], 'cr_warehouse_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_returns');
    }
};

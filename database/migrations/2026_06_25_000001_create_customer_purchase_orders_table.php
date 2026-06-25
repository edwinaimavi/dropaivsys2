<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('quote_id')
                ->nullable()
                ->constrained('quotes')
                ->nullOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('customer_branch_id')
                ->nullable()
                ->constrained('customer_branches')
                ->nullOnDelete();

            $table->string('order_type', 20);
            $table->string('purchase_order_number', 100)->nullable();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->date('notification_date')->nullable();
            $table->date('delivery_start_date')->nullable();
            $table->date('delivery_end_date')->nullable();

            $table->string('siaf_file_number', 100)->nullable();
            $table->string('acquisition_chart_number', 100)->nullable();
            $table->string('process_type', 100)->nullable();
            $table->string('billing_type', 20)->default('local');
            $table->boolean('affect_igv')->default(false);

            $table->text('observations')->nullable();

            $table->decimal('subtotal_exonerated', 15, 2)->default(0);
            $table->decimal('subtotal_taxed', 15, 2)->default(0);
            $table->decimal('igv', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->string('status', 30)->default('draft');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_order_number');
            $table->index('status');
            $table->index('notification_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_purchase_orders');
    }
};

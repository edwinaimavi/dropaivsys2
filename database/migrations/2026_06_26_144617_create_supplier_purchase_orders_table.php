<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('supplier_account_id')
                ->nullable()
                ->constrained('supplier_accounts')
                ->nullOnDelete();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->foreignId('customer_purchase_order_id')
                ->nullable()
                ->constrained('customer_purchase_orders')
                ->nullOnDelete();

            $table->foreignId('quote_id')
                ->nullable()
                ->constrained('quotes')
                ->nullOnDelete();

            $table->foreignId('market_study_id')
                ->nullable()
                ->constrained('market_studies')
                ->nullOnDelete();

            $table->string('order_type', 20);
            $table->string('payment_condition', 100)->nullable();
            $table->string('delivery_type', 100)->nullable();
            $table->string('transport_type', 100)->nullable();
            $table->text('shipping_address')->nullable();

            $table->foreignId('destination_ubigeo_id')
                ->nullable()
                ->constrained('ubigeos')
                ->nullOnDelete();

            $table->string('destination_text')->nullable();
            $table->string('payment_method', 100)->nullable();
            $table->string('document_type', 100)->nullable();
            $table->boolean('affect_igv')->default(true);
            $table->text('observations')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('igv', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->string('status', 30)->default('draft');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('supplier_id');
            $table->index('customer_purchase_order_id');
            $table->index('quote_id');
            $table->index('market_study_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_orders');
    }
};

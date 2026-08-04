<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recupera de forma segura una tabla vacía que MySQL pudo dejar tras fallar
        // la creación inicial de constraints (la migración aún no estaría registrada).
        if (Schema::hasTable('customer_order_profitability_analyses')) {
            Schema::drop('customer_order_profitability_analyses');
        }

        Schema::create('customer_order_profitability_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_purchase_order_id');
            $table->string('calculation_mode', 20);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('igv_rate', 5, 2)->default(18);
            $table->decimal('income_tax_rate', 5, 2)->default(29.5);
            foreach (['sale_total','sale_base','sale_igv','purchase_total','purchase_base','purchase_igv','freight_total','freight_base','freight_igv','expenses_without_receipt_total','other_expenses_total','linked_costs_total','gross_profit','operating_profit','estimated_income_tax','net_profit','igv_sales','igv_purchases','igv_difference'] as $column) {
                $table->decimal($column, 15, 2)->nullable();
            }
            $table->decimal('profitability_percentage', 10, 2)->default(0);
            $table->json('warnings')->nullable();
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['customer_purchase_order_id', 'calculation_mode'], 'copa_order_mode_unique');
            $table->foreign('customer_purchase_order_id', 'copa_customer_order_fk')->references('id')->on('customer_purchase_orders')->cascadeOnDelete();
            $table->foreign('currency_id', 'copa_currency_fk')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('calculated_by', 'copa_calculated_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'copa_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'copa_updated_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_profitability_analyses');
    }
};

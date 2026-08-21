<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('credit_days')->nullable()->after('payment_condition');
            $table->date('payment_due_date')->nullable()->after('credit_days');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['credit_days', 'payment_due_date']);
        });
    }
};

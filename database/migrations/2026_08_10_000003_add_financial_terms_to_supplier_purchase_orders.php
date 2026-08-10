<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->foreignId('payment_currency_id')->nullable()->after('currency_id')
                ->constrained('currencies')->nullOnDelete();
            $table->boolean('apply_exchange_rate')->default(false)->after('payment_currency_id');
            $table->decimal('exchange_rate', 18, 6)->nullable()->after('apply_exchange_rate');
            $table->decimal('total_purchase_currency', 18, 4)->default(0)->after('grand_total');
            $table->decimal('total_payment_currency', 18, 4)->default(0)->after('total_purchase_currency');
            $table->decimal('total_pen', 18, 4)->nullable()->after('total_payment_currency');
            $table->boolean('apply_advance')->default(false)->after('total_pen');
            $table->string('advance_type', 30)->nullable()->after('apply_advance');
            $table->decimal('advance_percentage', 8, 4)->nullable()->after('advance_type');
            $table->decimal('advance_amount', 18, 4)->default(0)->after('advance_percentage');
            $table->decimal('advance_amount_pen', 18, 4)->default(0)->after('advance_amount');
            $table->decimal('advance_paid_amount', 18, 4)->default(0)->after('advance_amount_pen');
            $table->decimal('advance_paid_amount_pen', 18, 4)->default(0)->after('advance_paid_amount');
            $table->string('advance_status', 30)->default('not_required')->after('advance_paid_amount_pen');
            $table->string('payment_status', 30)->default('pending')->after('advance_status');
            $table->index(['apply_advance', 'advance_status'], 'spo_advance_status_idx');
        });

        DB::table('supplier_purchase_orders')->update([
            'payment_currency_id' => DB::raw('currency_id'),
            'total_purchase_currency' => DB::raw('grand_total'),
            'total_payment_currency' => DB::raw('grand_total'),
            'payment_status' => DB::raw("CASE WHEN LOWER(COALESCE(payment_condition, '')) LIKE 'credito%' THEN 'credit' ELSE 'pending' END"),
        ]);

        $penCurrencyId = DB::table('currencies')->whereRaw('UPPER(code) = ?', ['PEN'])->value('id');
        if ($penCurrencyId) {
            DB::table('supplier_purchase_orders')
                ->where('currency_id', $penCurrencyId)
                ->update(['total_pen' => DB::raw('grand_total')]);
        }
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->dropIndex('spo_advance_status_idx');
            $table->dropConstrainedForeignId('payment_currency_id');
            $table->dropColumn([
                'apply_exchange_rate', 'exchange_rate', 'total_purchase_currency',
                'total_payment_currency', 'total_pen', 'apply_advance', 'advance_type',
                'advance_percentage', 'advance_amount', 'advance_amount_pen',
                'advance_paid_amount', 'advance_paid_amount_pen', 'advance_status', 'payment_status',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_purchase_orders', 'purchase_order_sequence')) {
                $table->unsignedInteger('purchase_order_sequence')->nullable()->after('code');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'purchase_order_year')) {
                $table->unsignedSmallInteger('purchase_order_year')->nullable()->after('purchase_order_sequence');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'purchase_order_bank_code')) {
                $table->string('purchase_order_bank_code', 50)->nullable()->after('purchase_order_year');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'requested_by')) {
                $table->string('requested_by')->nullable()->after('observations');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'request_department')) {
                $table->string('request_department')->nullable()->after('requested_by');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'authorized_by_name')) {
                $table->string('authorized_by_name')->nullable()->after('request_department');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'authorized_by_position')) {
                $table->string('authorized_by_position')->nullable()->after('authorized_by_name');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'delivery_text')) {
                $table->string('delivery_text')->nullable()->after('authorized_by_position');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'payment_terms_text')) {
                $table->string('payment_terms_text')->nullable()->after('delivery_text');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'purchase_instructions')) {
                $table->text('purchase_instructions')->nullable()->after('payment_terms_text');
            }

            if (!Schema::hasColumn('supplier_purchase_orders', 'important_note')) {
                $table->text('important_note')->nullable()->after('purchase_instructions');
            }

            $table->index(
                ['purchase_order_year', 'purchase_order_bank_code', 'purchase_order_sequence'],
                'spo_year_bank_sequence_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->dropIndex('spo_year_bank_sequence_index');

            $table->dropColumn([
                'purchase_order_sequence',
                'purchase_order_year',
                'purchase_order_bank_code',
                'requested_by',
                'request_department',
                'authorized_by_name',
                'authorized_by_position',
                'delivery_text',
                'payment_terms_text',
                'purchase_instructions',
                'important_note',
            ]);
        });
    }
};

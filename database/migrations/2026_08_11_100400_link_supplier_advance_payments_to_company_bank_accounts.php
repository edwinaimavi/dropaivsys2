<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_purchase_order_advance_payments', 'company_bank_account_id')) {
            Schema::table('supplier_purchase_order_advance_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('company_bank_account_id')->nullable()->after('supplier_account_id');
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys('supplier_purchase_order_advance_payments'))
            ->contains(fn (array $foreign) => in_array('company_bank_account_id', $foreign['columns'] ?? [], true));
        if (! $hasForeignKey) {
            Schema::table('supplier_purchase_order_advance_payments', function (Blueprint $table) {
                $table->foreign('company_bank_account_id', 'spo_advance_company_bank_fk')
                    ->references('id')->on('company_bank_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('supplier_purchase_order_advance_payments', 'company_bank_account_id')) {
            $foreignNames = collect(Schema::getForeignKeys('supplier_purchase_order_advance_payments'))
                ->filter(fn (array $foreign) => in_array('company_bank_account_id', $foreign['columns'] ?? [], true))
                ->pluck('name');
            foreach ($foreignNames as $foreignName) {
                Schema::table('supplier_purchase_order_advance_payments', fn (Blueprint $table) => $table->dropForeign($foreignName));
            }
            Schema::table('supplier_purchase_order_advance_payments', fn (Blueprint $table) => $table->dropColumn('company_bank_account_id'));
        }
    }
};

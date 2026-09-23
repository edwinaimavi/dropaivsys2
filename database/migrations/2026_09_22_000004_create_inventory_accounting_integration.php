<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 30);
            $table->string('name', 180);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('company_id', 'aa_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->unique(['company_id', 'code'], 'aa_company_code_uq');
            $table->index(['company_id', 'status'], 'aa_company_status_idx');
        });

        Schema::create('inventory_accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('inventory_account_id')->nullable();
            $table->unsignedBigInteger('receipt_offset_account_id')->nullable();
            $table->unsignedBigInteger('cost_of_sales_account_id')->nullable();
            $table->unsignedBigInteger('adjustment_gain_account_id')->nullable();
            $table->unsignedBigInteger('adjustment_loss_account_id')->nullable();
            $table->unsignedBigInteger('transfer_clearing_account_id')->nullable();
            $table->unsignedBigInteger('opening_offset_account_id')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('company_id', 'ias_company_uq');
            $table->foreign('company_id', 'ias_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('inventory_account_id', 'ias_inventory_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('receipt_offset_account_id', 'ias_receipt_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('cost_of_sales_account_id', 'ias_cogs_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('adjustment_gain_account_id', 'ias_adj_gain_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('adjustment_loss_account_id', 'ias_adj_loss_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('transfer_clearing_account_id', 'ias_transfer_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('opening_offset_account_id', 'ias_opening_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
            $table->foreign('updated_by', 'ias_updated_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('warehouse_kardex_movement_id');
            $table->date('entry_date');
            $table->string('cuo', 40);
            $table->string('correlative', 10);
            $table->string('source', 50)->default('inventory_kardex');
            $table->string('description', 255);
            $table->decimal('total_debit', 18, 2);
            $table->decimal('total_credit', 18, 2);
            $table->string('status', 20)->default('posted');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('warehouse_kardex_movement_id', 'aje_kardex_uq');
            $table->unique(['company_id', 'cuo'], 'aje_company_cuo_uq');
            $table->index(['company_id', 'warehouse_id', 'entry_date'], 'aje_period_idx');
            $table->foreign('company_id', 'aje_company_fk')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('warehouse_id', 'aje_warehouse_fk')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('warehouse_kardex_movement_id', 'aje_kardex_fk')->references('id')->on('warehouse_kardex_movements')->restrictOnDelete();
            $table->foreign('created_by', 'aje_created_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('accounting_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            $table->index(['journal_entry_id', 'account_id'], 'ajel_entry_account_idx');
            $table->foreign('journal_entry_id', 'ajel_entry_fk')->references('id')->on('accounting_journal_entries')->cascadeOnDelete();
            $table->foreign('account_id', 'ajel_account_fk')->references('id')->on('accounting_accounts')->restrictOnDelete();
        });

        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->string('accounting_cuo_snapshot', 40)->nullable()->after('exchange_rate');
            $table->string('accounting_entry_correlative_snapshot', 10)->nullable()->after('accounting_cuo_snapshot');
            $table->timestamp('accounting_posted_at')->nullable()->after('accounting_entry_correlative_snapshot');

            $table->index(['company_id', 'warehouse_id', 'accounting_cuo_snapshot'], 'wkm_accounting_cuo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->dropIndex('wkm_accounting_cuo_idx');
            $table->dropColumn([
                'accounting_cuo_snapshot',
                'accounting_entry_correlative_snapshot',
                'accounting_posted_at',
            ]);
        });

        Schema::dropIfExists('accounting_journal_entry_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('inventory_accounting_settings');
        Schema::dropIfExists('accounting_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_entry_expenses')) {
            return;
        }

        $addPettyCashExpenseForeign = ! Schema::hasColumn('warehouse_entry_expenses', 'petty_cash_expense_id')
            && Schema::hasTable('petty_cash_expenses');
        $addPettyCashReplenishmentForeign = ! Schema::hasColumn('warehouse_entry_expenses', 'petty_cash_replenishment_id')
            && Schema::hasTable('petty_cash_replenishments');

        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_entry_expenses', 'source_type')) {
                $table->string('source_type', 30)->nullable()->default('manual');
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'petty_cash_expense_id')) {
                $table->unsignedBigInteger('petty_cash_expense_id')->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'petty_cash_replenishment_id')) {
                $table->unsignedBigInteger('petty_cash_replenishment_id')->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'document_classification')) {
                $table->string('document_classification', 30)->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'official_document_type')) {
                $table->string('official_document_type', 40)->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'internal_document_type')) {
                $table->string('internal_document_type', 40)->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'exchanged_document_id')) {
                $table->unsignedBigInteger('exchanged_document_id')->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'exchanged_at')) {
                $table->timestamp('exchanged_at')->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable();
            }
            if (! Schema::hasColumn('warehouse_entry_expenses', 'official_document_path')) {
                $table->string('official_document_path')->nullable();
            }
        });

        if (! Schema::hasIndex('warehouse_entry_expenses', ['petty_cash_expense_id'])) {
            Schema::table('warehouse_entry_expenses', fn (Blueprint $table) => $table
                ->index('petty_cash_expense_id', 'wee_petty_cash_expense_index'));
        }
        if (! Schema::hasIndex('warehouse_entry_expenses', ['petty_cash_replenishment_id'])) {
            Schema::table('warehouse_entry_expenses', fn (Blueprint $table) => $table
                ->index('petty_cash_replenishment_id', 'wee_petty_cash_replenishment_index'));
        }
        if (! Schema::hasIndex('warehouse_entry_expenses', ['exchanged_document_id'])) {
            Schema::table('warehouse_entry_expenses', fn (Blueprint $table) => $table
                ->index('exchanged_document_id', 'wee_exchanged_document_index'));
        }
        if (! Schema::hasIndex('warehouse_entry_expenses', ['source_type', 'status'])) {
            Schema::table('warehouse_entry_expenses', fn (Blueprint $table) => $table
                ->index(['source_type', 'status'], 'wee_source_status_index'));
        }

        if ($addPettyCashExpenseForeign) {
            Schema::table('warehouse_entry_expenses', fn (Blueprint $table) => $table
                ->foreign('petty_cash_expense_id', 'wee_petty_cash_expense_fk')
                ->references('id')->on('petty_cash_expenses')->restrictOnDelete());
        }
        if ($addPettyCashReplenishmentForeign) {
            Schema::table('warehouse_entry_expenses', fn (Blueprint $table) => $table
                ->foreign('petty_cash_replenishment_id', 'wee_petty_cash_replenishment_fk')
                ->references('id')->on('petty_cash_replenishments')->nullOnDelete());
        }

        DB::table('warehouse_entry_expenses')->whereNull('source_type')->update(['source_type' => 'manual']);
        DB::table('warehouse_entry_expenses')->whereNull('document_classification')->update([
            'document_classification' => DB::raw("CASE WHEN UPPER(COALESCE(document_type, '')) IN ('FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'RECIBO_POR_HONORARIOS') THEN 'official' ELSE 'non_official' END"),
        ]);
        DB::table('warehouse_entry_expenses')
            ->whereNull('official_document_type')
            ->whereIn(DB::raw("UPPER(COALESCE(document_type, ''))"), ['FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'RECIBO_POR_HONORARIOS'])
            ->update([
                'official_document_type' => DB::raw("CASE UPPER(document_type) WHEN 'FACTURA' THEN 'factura' WHEN 'BOLETA' THEN 'boleta' ELSE 'recibo_por_honorarios' END"),
            ]);
        DB::table('warehouse_entry_expenses')
            ->whereNull('internal_document_type')
            ->whereNotIn(DB::raw("UPPER(COALESCE(document_type, ''))"), ['FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'RECIBO_POR_HONORARIOS'])
            ->update([
                'internal_document_type' => DB::raw("CASE WHEN UPPER(COALESCE(document_type, '')) IN ('RECIBO', 'RECIBO_INTERNO') THEN 'recibo_interno' ELSE 'sin_comprobante' END"),
            ]);

        if (Schema::hasTable('warehouse_entry_expense_documents')) {
            Schema::table('warehouse_entry_expense_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('warehouse_entry_expense_documents', 'source_document_id')) {
                    $table->unsignedBigInteger('source_document_id')->nullable();
                }
                if (! Schema::hasColumn('warehouse_entry_expense_documents', 'source_context')) {
                    $table->string('source_context', 40)->nullable();
                }
            });

            if (! Schema::hasIndex('warehouse_entry_expense_documents', ['source_document_id'])) {
                Schema::table('warehouse_entry_expense_documents', fn (Blueprint $table) => $table
                    ->index('source_document_id', 'weedoc_source_document_index'));
            }
        }
    }

    public function down(): void
    {
        // No se eliminan columnas ni datos para que un rollback accidental sea seguro en producción.
    }
};

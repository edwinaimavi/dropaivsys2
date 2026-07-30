<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'petty_cash_expenses_document_unique';

    public function up(): void
    {
        $duplicates = DB::table('petty_cash_expenses')
            ->selectRaw('UPPER(TRIM(document_type)) as document_type')
            ->selectRaw('UPPER(TRIM(document_series)) as document_series')
            ->selectRaw('TRIM(document_correlative) as document_correlative')
            ->selectRaw('TRIM(supplier_ruc) as supplier_ruc')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('document_type')
            ->whereNotNull('document_series')
            ->whereNotNull('document_correlative')
            ->whereNotNull('supplier_ruc')
            ->groupByRaw(
                'UPPER(TRIM(document_type)), UPPER(TRIM(document_series)), '
                . 'TRIM(document_correlative), TRIM(supplier_ruc)'
            )
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            Log::warning(
                'No se creó el índice único de comprobantes de Caja Chica porque existen duplicados históricos.',
                ['duplicates' => $duplicates->map(fn ($item) => (array) $item)->all()]
            );

            return;
        }

        Schema::table('petty_cash_expenses', function (Blueprint $table) {
            $table->unique(
                ['document_type', 'document_series', 'document_correlative', 'supplier_ruc'],
                self::INDEX
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('petty_cash_expenses', self::INDEX)) {
            Schema::table('petty_cash_expenses', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }
};

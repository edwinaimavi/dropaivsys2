<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hasta ahora todo adjunto de un costo representaba su comprobante principal.
        // Se conserva el archivo y se clasifica como factura/comprobante tributario.
        DB::table('warehouse_entry_expense_documents')
            ->where(function ($query) {
                $query->whereNull('document_type')
                    ->orWhereNotIn('document_type', ['invoice', 'payment_proof']);
            })
            ->update(['document_type' => 'invoice']);

        Schema::table('warehouse_entry_expense_documents', function (Blueprint $table) {
            $table->index(
                ['warehouse_entry_expense_id', 'document_type', 'status'],
                'weedoc_expense_type_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_entry_expense_documents', function (Blueprint $table) {
            $table->dropIndex('weedoc_expense_type_status_idx');
        });
    }
};

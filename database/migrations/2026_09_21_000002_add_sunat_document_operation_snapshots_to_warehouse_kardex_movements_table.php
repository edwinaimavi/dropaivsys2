<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->date('document_date_snapshot')->nullable()->after('document_type');
            $table->string('sunat_document_type_code_snapshot', 20)->nullable()->after('document_date_snapshot');
            $table->string('sunat_operation_type_code_snapshot', 20)->nullable()->after('operation_type');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->dropColumn([
                'document_date_snapshot',
                'sunat_document_type_code_snapshot',
                'sunat_operation_type_code_snapshot',
            ]);
        });
    }
};

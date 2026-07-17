<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_invoice_series', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'document_type', 'serie', 'environment'],
                'ei_series_company_type_serie_env_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('electronic_invoice_series', function (Blueprint $table) {
            $table->dropUnique('ei_series_company_type_serie_env_unique');
        });
    }
};

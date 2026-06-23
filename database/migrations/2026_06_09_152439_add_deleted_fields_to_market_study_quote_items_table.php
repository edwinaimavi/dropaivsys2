<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_study_quote_items', function (Blueprint $table) {

            $table->unsignedBigInteger('deleted_by')
                ->nullable()
                ->after('updated_by');

            $table->softDeletes()
                ->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('market_study_quote_items', function (Blueprint $table) {

            $table->dropColumn('deleted_by');

            $table->dropSoftDeletes();
        });
    }
};

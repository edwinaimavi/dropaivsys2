<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {

            $table->enum(
                'code_type',
                [
                    'SIGA/SISMED',
                    'SAP/IETSI'
                ]
            )
                ->nullable()
                ->after('code');

            $table->string(
                'institutional_code',
                100
            )
                ->nullable()
                ->after('code_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {

            $table->dropColumn([
                'code_type',
                'institutional_code'
            ]);
        });
    }
};

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
        Schema::table('customers', function (Blueprint $table) {

            $table->string('channel', 100)
                ->nullable()
                ->after('ruc');

            $table->string('subchannel', 100)
                ->nullable()
                ->after('channel');

            $table->boolean('withholding_agent')
                ->default(false)
                ->after('subchannel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {

            $table->dropColumn([
                'channel',
                'subchannel',
                'withholding_agent'
            ]);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->date('end_date')->nullable()->change();
            $table->text('close_observation')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_boxes', function (Blueprint $table) {
            $table->dropColumn('close_observation');
            $table->date('end_date')->nullable(false)->change();
        });
    }
};

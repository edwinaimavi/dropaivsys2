<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('issuer_department', 50)->nullable()->after('additional_observations');
            $table->string('contact_number', 80)->nullable()->after('issuer_department');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['issuer_department', 'contact_number']);
        });
    }
};

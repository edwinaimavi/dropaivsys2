<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {

            $table->enum('person_type', ['natural', 'juridica'])
                ->default('natural')
                ->after('id');

            $table->string('business_name')->nullable()->after('last_name');

            $table->string('ruc', 11)->nullable()->after('document_number');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['person_type', 'business_name', 'ruc']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {

            $table->id();

            $table->string('business_name');
            $table->string('trade_name')->nullable();

            $table->string('ruc', 11)->unique();

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();

            $table->text('address')->nullable();

            $table->string('logo')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
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
        Schema::create('supplier_accounts', function (Blueprint $table) {

            $table->id();

            // RELACIONES
            $table->unsignedBigInteger('supplier_id');

            $table->unsignedBigInteger('bank_id');

            $table->unsignedBigInteger('currency_id');

            // DATOS DE LA CUENTA
            $table->string('account_holder', 255);

            $table->string('account_number', 100);

            $table->string('cci', 100)->nullable();

            $table->enum('is_detraction', [
                'YES',
                'NO'
            ])->default('NO');

            $table->enum('status', [
                'ACTIVE',
                'INACTIVE'
            ])->default('ACTIVE');

            $table->text('observation')->nullable();

            // AUDITORÍA
            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();

            $table->softDeletes();

            // FOREIGN KEYS
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('cascade');

            $table->foreign('bank_id')
                ->references('id')
                ->on('banks');

            $table->foreign('currency_id')
                ->references('id')
                ->on('currencies');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('deleted_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_accounts');
    }
};

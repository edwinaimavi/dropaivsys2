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
        Schema::create('customer_branches', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('customer_id');

            $table->string('branch_name');
            $table->string('branch_type')->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->unsignedBigInteger('ubigeo_id')->nullable();
            $table->string('address')->nullable();
            $table->string('reference')->nullable();

            $table->string('voucher_type')->nullable();

            $table->enum('generate_guide', [
                'SI',
                'NO'
            ])->default('NO');

            $table->string('payment_condition')->nullable();

            $table->boolean('is_main')->default(false);

            $table->boolean('status')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');

            $table->foreign('ubigeo_id')
                ->references('id')
                ->on('ubigeos')
                ->nullOnDelete();


            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by')
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
        Schema::dropIfExists('customer_branches');
    }
};

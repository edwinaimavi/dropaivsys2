<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_replenishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_box_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->date('replenishment_date');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 50)->nullable();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bank_account', 100)->nullable();
            $table->string('reference_number', 150)->nullable();
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['petty_cash_box_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_replenishments');
    }
};

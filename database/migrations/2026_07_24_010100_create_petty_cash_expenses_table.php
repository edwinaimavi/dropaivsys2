<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_box_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('item_number');
            $table->date('expense_date');
            $table->string('document_type', 40)->nullable();
            $table->string('document_number', 100)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_ruc', 11)->nullable();
            $table->string('supplier_name', 255);
            $table->string('concept', 500);
            $table->decimal('amount', 14, 2);
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['petty_cash_box_id', 'item_number']);
            $table->index(['petty_cash_box_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_expenses');
    }
};

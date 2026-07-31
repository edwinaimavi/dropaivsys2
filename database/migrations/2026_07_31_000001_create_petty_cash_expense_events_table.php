<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_expense_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('event', 80);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['petty_cash_expense_id', 'event'], 'pc_expense_events_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_expense_events');
    }
};

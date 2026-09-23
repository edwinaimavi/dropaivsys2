<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_note_derivations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('assistance_note_id')->constrained('assistance_notes')->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('derived_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'assistance_note_id', 'created_at'], 'assistance_note_derivations_note_idx');
            $table->index(['company_id', 'to_user_id', 'created_at'], 'assistance_note_derivations_recipient_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_note_derivations');
    }
};

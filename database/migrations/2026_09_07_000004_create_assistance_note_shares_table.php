<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_note_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('assistance_note_id')->constrained('assistance_notes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shared_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['assistance_note_id', 'user_id'], 'assistance_note_user_unique');
            $table->index(['company_id', 'user_id'], 'assistance_note_shares_company_user_idx');
            $table->index(['company_id', 'shared_by_user_id'], 'assistance_note_shares_company_sharer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_note_shares');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_note_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('assistance_note_id')->constrained('assistance_notes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('comment');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'assistance_note_id', 'created_at'], 'assistance_note_comments_note_idx');
            $table->index(['company_id', 'user_id'], 'assistance_note_comments_company_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_note_comments');
    }
};

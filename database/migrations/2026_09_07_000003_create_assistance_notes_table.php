<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistance_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();

            $table->string('title', 180);
            $table->longText('content');
            $table->enum('visibility', ['private', 'shared'])->default('private');
            $table->enum('status', ['pending', 'in_progress', 'derived', 'completed'])->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->date('follow_up_date')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'created_by_user_id'], 'assistance_notes_company_creator_idx');
            $table->index(['company_id', 'responsible_user_id', 'status'], 'assistance_notes_company_responsible_status_idx');
            $table->index(['company_id', 'follow_up_date'], 'assistance_notes_company_follow_up_idx');
            $table->index(['company_id', 'due_at'], 'assistance_notes_company_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistance_notes');
    }
};

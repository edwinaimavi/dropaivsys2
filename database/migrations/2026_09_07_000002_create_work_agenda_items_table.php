<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();

            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->date('activity_date');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('activity_type', 50)->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->string('location', 180)->nullable();
            $table->dateTime('reminder_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'created_by_user_id'], 'work_agenda_company_creator_idx');
            $table->index(['company_id', 'responsible_user_id', 'activity_date'], 'work_agenda_company_responsible_date_idx');
            $table->index(['company_id', 'status', 'activity_date'], 'work_agenda_company_status_date_idx');
            $table->index(['company_id', 'activity_type', 'activity_date'], 'work_agenda_company_type_date_idx');
            $table->index(['company_id', 'activity_date'], 'work_agenda_company_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_agenda_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_agenda_item_derivations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_agenda_item_id')->constrained('work_agenda_items')->cascadeOnDelete();
            $table->foreignId('from_assignment_id')->constrained('work_agenda_item_assignments')->restrictOnDelete();
            $table->foreignId('to_assignment_id')->constrained('work_agenda_item_assignments')->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('derived_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->dateTime('derived_at');
            $table->timestamps();

            $table->unique('from_assignment_id', 'work_agenda_derivation_from_assignment_unique');
            $table->index(['work_agenda_item_id', 'derived_at'], 'work_agenda_derivation_item_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_agenda_item_derivations');
    }
};

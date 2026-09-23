<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_agenda_item_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_agenda_item_id')->constrained('work_agenda_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'derived', 'cancelled'])->default('pending');
            $table->dateTime('assigned_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['work_agenda_item_id', 'status'], 'work_agenda_assignment_item_status_idx');
            $table->index(['user_id', 'status'], 'work_agenda_assignment_user_status_idx');
        });

        DB::table('work_agenda_items')
            ->whereNotNull('responsible_user_id')
            ->orderBy('id')
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $exists = DB::table('work_agenda_item_assignments')
                        ->where('work_agenda_item_id', $item->id)
                        ->where('user_id', $item->responsible_user_id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $assignedAt = $item->created_at ?? now();
                    $status = in_array($item->status, ['pending', 'in_progress', 'completed', 'cancelled'], true)
                        ? $item->status
                        : 'pending';

                    DB::table('work_agenda_item_assignments')->insert([
                        'work_agenda_item_id' => $item->id,
                        'user_id' => $item->responsible_user_id,
                        'assigned_by_user_id' => $item->created_by_user_id,
                        'cancelled_by_user_id' => null,
                        'status' => $status,
                        'assigned_at' => $assignedAt,
                        'started_at' => null,
                        'completed_at' => $status === 'completed' ? ($item->completed_at ?? $item->updated_at) : null,
                        'cancelled_at' => $status === 'cancelled' ? ($item->updated_at ?? $assignedAt) : null,
                        'created_at' => $assignedAt,
                        'updated_at' => $item->updated_at ?? $assignedAt,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_agenda_item_assignments');
    }
};

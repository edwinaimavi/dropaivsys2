<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_amount_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->decimal('previous_amount', 14, 2)->nullable();
            $table->decimal('approved_amount', 14, 2);
            $table->string('currency', 10);
            $table->foreignId('approved_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('approved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_amount_histories');
    }
};

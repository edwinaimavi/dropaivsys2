<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_expenses', function (Blueprint $table) {
            $table->string('approval_status', 30)->default('pendiente_aprobacion')->after('status');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_by_user_id');
            $table->foreignId('rejected_by_user_id')->nullable()->after('rejected_at')
                ->constrained('users')->nullOnDelete();
            $table->text('approval_observation')->nullable()->after('rejected_by_user_id');
            $table->index(['petty_cash_box_id', 'status', 'approval_status'], 'pc_expenses_approval_index');
        });

        DB::table('petty_cash_expenses')->update([
            'approval_status' => 'aprobado',
            'approved_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('petty_cash_expenses', function (Blueprint $table) {
            $table->dropIndex('pc_expenses_approval_index');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn([
                'approval_status',
                'approved_at',
                'rejected_at',
                'approval_observation',
            ]);
        });
    }
};

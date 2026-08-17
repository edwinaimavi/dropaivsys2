<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entry_expenses', function (Blueprint $table) {
            $table->foreignId('general_cash_box_id')->nullable()->after('petty_cash_replenishment_id')
                ->constrained('general_cash_boxes')->restrictOnDelete();
            $table->foreignId('general_cash_movement_id')->nullable()->after('general_cash_box_id')
                ->constrained('general_cash_movements')->restrictOnDelete();
            $table->foreignId('company_bank_account_id')->nullable()->after('general_cash_movement_id')
                ->constrained('company_bank_accounts')->restrictOnDelete();
            $table->foreignId('bank_movement_id')->nullable()->after('company_bank_account_id')
                ->constrained('bank_movements')->restrictOnDelete();
            $table->string('approval_status', 30)->default('pending')->after('status');
            $table->foreignId('approved_by')->nullable()->after('updated_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_observation')->nullable()->after('approved_at');

            $table->index(['approval_status', 'status'], 'wee_approval_status_index');
            $table->unique('general_cash_movement_id', 'wee_general_cash_movement_unique');
            $table->unique('bank_movement_id', 'wee_bank_movement_unique');
        });

        // Los costos ya existentes eran definitivos antes de incorporar el flujo de aprobación.
        DB::table('warehouse_entry_expenses')->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        // Se conserva la trazabilidad financiera ante un rollback accidental.
    }
};

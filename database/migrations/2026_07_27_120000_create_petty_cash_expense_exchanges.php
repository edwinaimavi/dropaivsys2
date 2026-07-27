<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_expense_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_box_id')->constrained()->cascadeOnDelete();
            $table->date('exchange_date');
            $table->string('document_type', 20);
            $table->string('document_series', 20);
            $table->string('document_correlative', 50);
            $table->decimal('total_amount', 14, 2);
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['petty_cash_box_id', 'status']);
        });

        Schema::create('petty_cash_expense_exchange_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('petty_cash_expense_exchanges')->cascadeOnDelete();
            $table->foreignId('petty_cash_expense_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('concept', 500)->nullable();
            $table->string('receipt_type', 20)->nullable();
            $table->string('receipt_series', 20)->nullable();
            $table->string('receipt_correlative', 50)->nullable();
            $table->timestamps();

            $table->unique('petty_cash_expense_id', 'pc_exchange_item_expense_unique');
        });

        Schema::table('petty_cash_expenses', function (Blueprint $table) {
            $table->string('exchange_status', 30)->default('NO_APLICA')->after('approval_status');
            $table->dateTime('exchanged_at')->nullable()->after('exchange_status');
            $table->foreignId('exchange_id')->nullable()->after('exchanged_at')
                ->constrained('petty_cash_expense_exchanges')->nullOnDelete();
            $table->index(['petty_cash_box_id', 'exchange_status'], 'pc_expenses_exchange_status_index');
        });

        DB::table('petty_cash_expenses')
            ->whereRaw('UPPER(document_type) = ?', ['RECIBO'])
            ->update(['exchange_status' => 'PENDIENTE_CANJE']);
    }

    public function down(): void
    {
        Schema::table('petty_cash_expenses', function (Blueprint $table) {
            $table->dropIndex('pc_expenses_exchange_status_index');
            $table->dropConstrainedForeignId('exchange_id');
            $table->dropColumn(['exchange_status', 'exchanged_at']);
        });
        Schema::dropIfExists('petty_cash_expense_exchange_items');
        Schema::dropIfExists('petty_cash_expense_exchanges');
    }
};

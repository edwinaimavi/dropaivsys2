<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_expense_exchange_documents', function (Blueprint $table) {
            $table->text('observation')->nullable()->after('concept');
            $table->foreignId('edited_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('edited_by');
            $table->foreignId('reversed_by')->nullable()->after('edited_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            $table->text('reversal_reason')->nullable()->after('reversed_at');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_expense_exchange_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropConstrainedForeignId('edited_by');
            $table->dropColumn(['observation', 'edited_at', 'reversed_at', 'reversal_reason']);
        });
    }
};

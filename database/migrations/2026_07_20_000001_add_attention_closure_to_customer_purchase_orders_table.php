<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->string('attention_result', 30)->nullable()->after('status');
            $table->text('attention_observation')->nullable()->after('attention_result');
            $table->timestamp('attention_closed_at')->nullable()->after('attention_observation');
            $table->foreignId('attention_closed_by')->nullable()->after('attention_closed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('attention_document_path')->nullable()->after('attention_closed_by');
            $table->string('attention_document_name')->nullable()->after('attention_document_path');
            $table->string('attention_document_mime', 100)->nullable()->after('attention_document_name');

            $table->index(['attention_result', 'attention_closed_at'], 'cpo_attention_closure_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->dropIndex('cpo_attention_closure_index');
            $table->dropConstrainedForeignId('attention_closed_by');
            $table->dropColumn([
                'attention_result',
                'attention_observation',
                'attention_closed_at',
                'attention_document_path',
                'attention_document_name',
                'attention_document_mime',
            ]);
        });
    }
};

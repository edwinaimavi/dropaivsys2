<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_issuers', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 11)->unique();
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('status', 100)->nullable();
            $table->string('condition', 100)->nullable();
            $table->string('source', 20)->nullable();
            $table->json('api_response')->nullable();
            $table->dateTime('last_lookup_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('petty_cash_expense_exchanges', function (Blueprint $table) {
            $table->foreignId('document_issuer_id')->nullable()->after('petty_cash_box_id')
                ->constrained('document_issuers')->nullOnDelete();
            $table->string('issuer_ruc', 11)->nullable()->after('document_correlative');
            $table->string('issuer_business_name')->nullable()->after('issuer_ruc');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_expense_exchanges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_issuer_id');
            $table->dropColumn(['issuer_ruc', 'issuer_business_name']);
        });
        Schema::dropIfExists('document_issuers');
    }
};

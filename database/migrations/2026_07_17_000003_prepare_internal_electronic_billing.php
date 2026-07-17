<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->foreignId('customer_branch_id')->nullable()->after('customer_id')
                ->constrained('customer_branches')->nullOnDelete();
            $table->text('api_error')->nullable()->after('api_message');
            $table->timestamp('api_accepted_at')->nullable()->after('api_sent_at');
            $table->string('external_pdf_path')->nullable()->after('pdf_path');
        });

        Schema::table('electronic_invoices', function (Blueprint $table) {
            foreach (['taxable_amount', 'exonerated_amount', 'unaffected_amount', 'free_amount',
                'discount_total', 'subtotal', 'igv_amount', 'isc_amount', 'icbper_amount',
                'other_charges', 'total_taxes', 'total_amount'] as $column) {
                $table->decimal($column, 20, 10)->default(0)->change();
            }
        });

        Schema::table('electronic_invoice_items', function (Blueprint $table) {
            foreach (['quantity', 'unit_value', 'unit_price', 'discount_amount', 'subtotal',
                'igv_base', 'igv_amount', 'total_taxes', 'line_total'] as $column) {
                $table->decimal($column, 20, 10)->default(0)->change();
            }
        });

        DB::table('electronic_invoice_settings')->orderBy('id')->each(function ($setting) {
            $updates = [];
            foreach (['api_token', 'user_token', 'sol_password'] as $column) {
                $value = $setting->{$column};
                if (! filled($value)) {
                    continue;
                }
                try {
                    Crypt::decryptString($value);
                } catch (\Throwable) {
                    $updates[$column] = Crypt::encryptString($value);
                }
            }
            if ($updates !== []) {
                DB::table('electronic_invoice_settings')->where('id', $setting->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        Schema::table('electronic_invoices', function (Blueprint $table) {
            $table->dropForeign(['customer_branch_id']);
            $table->dropColumn(['customer_branch_id', 'api_error', 'api_accepted_at', 'external_pdf_path']);
        });
    }
};

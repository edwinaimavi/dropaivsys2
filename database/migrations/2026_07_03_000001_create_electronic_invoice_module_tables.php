<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electronic_invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('provider', 50)->default('apisperu');
            $table->string('environment', 20)->default('beta');
            $table->string('api_base_url')->nullable();
            $table->text('api_token')->nullable();
            $table->text('user_token')->nullable();
            $table->string('ruc', 20)->nullable();
            $table->string('business_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->text('address')->nullable();
            $table->string('ubigeo', 10)->nullable();
            $table->string('department')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('sol_user')->nullable();
            $table->text('sol_password')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'environment', 'is_active'], 'ei_settings_company_env_active_idx');
        });

        Schema::create('electronic_invoice_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('document_type', 2);
            $table->string('serie', 10);
            $table->unsignedInteger('current_number')->default(0);
            $table->unsignedInteger('next_number')->default(1);
            $table->string('environment', 20)->default('beta');
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'document_type', 'serie', 'environment'], 'ei_series_unique_lookup_idx');
        });

        Schema::create('electronic_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('customer_purchase_order_id')->nullable()->constrained('customer_purchase_orders')->nullOnDelete();
            $table->foreignId('warehouse_entry_id')->nullable()->constrained('warehouse_entries')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('electronic_invoice_series')->nullOnDelete();
            $table->string('document_type', 2);
            $table->string('serie', 10);
            $table->string('correlativo', 20);
            $table->string('full_number', 30);
            $table->date('issue_date');
            $table->time('issue_time')->nullable();
            $table->date('due_date')->nullable();
            $table->string('operation_type', 10)->default('0101');
            $table->string('currency_code', 5)->default('PEN');
            $table->string('payment_type', 20)->default('Contado');
            $table->string('payment_method')->nullable();
            $table->string('payment_condition')->nullable();
            $table->string('client_document_type', 5)->nullable();
            $table->string('client_document_number', 20)->nullable();
            $table->string('client_name')->nullable();
            $table->text('client_address')->nullable();
            $table->string('client_ubigeo', 10)->nullable();
            $table->string('client_department')->nullable();
            $table->string('client_province')->nullable();
            $table->string('client_district')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('company_ruc', 20)->nullable();
            $table->string('company_business_name')->nullable();
            $table->string('company_trade_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_ubigeo', 10)->nullable();
            $table->string('company_department')->nullable();
            $table->string('company_province')->nullable();
            $table->string('company_district')->nullable();
            $table->string('purchase_order_number')->nullable();
            $table->string('siaf_number')->nullable();
            $table->string('process_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->text('delivery_note')->nullable();
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('exonerated_amount', 15, 2)->default(0);
            $table->decimal('unaffected_amount', 15, 2)->default(0);
            $table->decimal('free_amount', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('igv_amount', 15, 2)->default(0);
            $table->decimal('isc_amount', 15, 2)->default(0);
            $table->decimal('icbper_amount', 15, 2)->default(0);
            $table->decimal('other_charges', 15, 2)->default(0);
            $table->decimal('total_taxes', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('total_text')->nullable();
            $table->json('api_payload')->nullable();
            $table->json('api_response')->nullable();
            $table->string('api_provider', 50)->default('apisperu');
            $table->string('api_endpoint')->nullable();
            $table->timestamp('api_sent_at')->nullable();
            $table->integer('api_status_code')->nullable();
            $table->boolean('api_success')->nullable();
            $table->text('api_message')->nullable();
            $table->string('sunat_status', 30)->nullable();
            $table->string('sunat_code')->nullable();
            $table->text('sunat_description')->nullable();
            $table->json('sunat_notes')->nullable();
            $table->string('cdr_ticket')->nullable();
            $table->string('hash')->nullable();
            $table->string('xml_name')->nullable();
            $table->string('pdf_name')->nullable();
            $table->string('cdr_name')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_sent_to_sunat')->default(false);
            $table->boolean('is_voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->text('voided_reason')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'document_type', 'serie', 'correlativo'], 'ei_unique_number_idx');
            $table->index(['status', 'sunat_status', 'issue_date'], 'ei_status_issue_idx');
        });

        Schema::create('electronic_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_id')->constrained('electronic_invoices')->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('quote_item_id')->nullable()->constrained('quote_items')->nullOnDelete();
            $table->foreignId('customer_purchase_order_item_id')->nullable()->constrained('customer_purchase_order_items')->nullOnDelete();
            $table->foreignId('warehouse_entry_item_id')->nullable()->constrained('warehouse_entry_items')->nullOnDelete();
            $table->foreignId('kardex_movement_id')->nullable()->constrained('warehouse_kardex_movements')->nullOnDelete();
            $table->unsignedInteger('item_number');
            $table->string('product_code')->nullable();
            $table->string('sunat_product_code')->nullable();
            $table->text('description');
            $table->string('commercial_name')->nullable();
            $table->string('billing_name')->nullable();
            $table->string('unit_code', 10)->default('NIU');
            $table->string('unit_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('presentation_name')->nullable();
            $table->string('lot_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('origin')->nullable();
            $table->string('health_registration')->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_value', 15, 6)->default(0);
            $table->decimal('unit_price', 15, 6)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('igv_base', 15, 2)->default(0);
            $table->decimal('igv_amount', 15, 2)->default(0);
            $table->decimal('igv_percentage', 5, 2)->default(18);
            $table->string('tax_affectation_code', 5)->default('10');
            $table->string('tax_code', 10)->default('1000');
            $table->string('tax_name', 50)->default('IGV');
            $table->string('tax_type_code', 10)->default('VAT');
            $table->decimal('total_taxes', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('electronic_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_id')->constrained('electronic_invoices')->cascadeOnDelete();
            $table->string('payment_type', 20);
            $table->unsignedInteger('quota_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('electronic_invoice_legends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_id')->constrained('electronic_invoices')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('description')->nullable();
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('electronic_invoice_related_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('electronic_invoice_id');
            $table->string('relation_type', 50);
            $table->string('document_type', 10)->nullable();
            $table->string('serie', 20)->nullable();
            $table->string('number', 30)->nullable();
            $table->string('full_number', 50)->nullable();
            $table->text('description')->nullable();
            $table->date('issue_date')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
            $table->foreign('electronic_invoice_id', 'ei_rel_docs_invoice_fk')
                ->references('id')
                ->on('electronic_invoices')
                ->cascadeOnDelete();
        });

        Schema::create('electronic_invoice_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('electronic_invoice_id');
            $table->string('file_type', 20);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('source', 30)->default('local');
            $table->boolean('is_generated')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('electronic_invoice_id', 'ei_files_invoice_fk')
                ->references('id')
                ->on('electronic_invoices')
                ->cascadeOnDelete();
        });

        Schema::create('electronic_invoice_api_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('electronic_invoice_id')->nullable();
            $table->string('provider', 50)->default('apisperu');
            $table->string('operation', 50);
            $table->string('method', 10)->default('POST');
            $table->string('endpoint')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_payload')->nullable();
            $table->boolean('success')->default(false);
            $table->text('message')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->foreign('electronic_invoice_id', 'ei_api_logs_invoice_fk')
                ->references('id')
                ->on('electronic_invoices')
                ->nullOnDelete();
        });

        Schema::create('electronic_invoice_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('electronic_invoice_id');
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->string('sunat_code')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
            $table->foreign('electronic_invoice_id', 'ei_status_invoice_fk')
                ->references('id')
                ->on('electronic_invoices')
                ->cascadeOnDelete();
        });

        Schema::create('sunat_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('catalog_code', 50);
            $table->string('item_code', 20);
            $table->string('description');
            $table->string('short_name')->nullable();
            $table->json('extra_data')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
            $table->unique(['catalog_code', 'item_code'], 'sunat_catalog_unique_item_idx');
        });

        Schema::create('electronic_invoice_voided', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_id')->constrained('electronic_invoices')->cascadeOnDelete();
            $table->string('voided_number')->nullable();
            $table->date('communication_date')->nullable();
            $table->date('document_date')->nullable();
            $table->text('reason');
            $table->string('ticket')->nullable();
            $table->string('sunat_status')->nullable();
            $table->string('sunat_code')->nullable();
            $table->text('sunat_description')->nullable();
            $table->json('api_payload')->nullable();
            $table->json('api_response')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('electronic_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electronic_invoice_id')->nullable()->constrained('electronic_invoices')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('document_type', 2);
            $table->string('serie', 10);
            $table->string('correlativo', 20);
            $table->string('full_number', 30);
            $table->string('note_type_code', 10)->nullable();
            $table->text('reason')->nullable();
            $table->date('issue_date');
            $table->string('currency_code', 5)->default('PEN');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('igv_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->json('api_payload')->nullable();
            $table->json('api_response')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('sunat_status', 30)->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_notes');
        Schema::dropIfExists('electronic_invoice_voided');
        Schema::dropIfExists('sunat_catalog_items');
        Schema::dropIfExists('electronic_invoice_status_histories');
        Schema::dropIfExists('electronic_invoice_api_logs');
        Schema::dropIfExists('electronic_invoice_files');
        Schema::dropIfExists('electronic_invoice_related_documents');
        Schema::dropIfExists('electronic_invoice_legends');
        Schema::dropIfExists('electronic_invoice_payments');
        Schema::dropIfExists('electronic_invoice_items');
        Schema::dropIfExists('electronic_invoices');
        Schema::dropIfExists('electronic_invoice_series');
        Schema::dropIfExists('electronic_invoice_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->foreignId('shipping_agency_id')
                ->nullable()
                ->after('shipping_address')
                ->constrained('shipping_agencies')
                ->nullOnDelete();

            $table->foreignId('shipping_agency_branch_id')
                ->nullable()
                ->after('shipping_agency_id')
                ->constrained('shipping_agency_branches')
                ->nullOnDelete();

            $table->foreignId('shipping_agency_contact_id')
                ->nullable()
                ->after('shipping_agency_branch_id')
                ->constrained('shipping_agency_contacts')
                ->nullOnDelete();

            $table->string('shipping_reference')
                ->nullable()
                ->after('shipping_agency_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_agency_contact_id');
            $table->dropConstrainedForeignId('shipping_agency_branch_id');
            $table->dropConstrainedForeignId('shipping_agency_id');
            $table->dropColumn('shipping_reference');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_order_trackings', function (Blueprint $table) {
            $table->foreignId('shipping_agency_id')
                ->nullable()
                ->after('estimated_date')
                ->constrained('shipping_agencies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_order_trackings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_agency_id');
        });
    }
};

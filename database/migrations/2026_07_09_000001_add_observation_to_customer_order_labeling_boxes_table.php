<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customer_order_labeling_boxes', 'observation')) {
            Schema::table('customer_order_labeling_boxes', function (Blueprint $table) {
                $table->text('observation')->nullable()->after('box_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_order_labeling_boxes', 'observation')) {
            Schema::table('customer_order_labeling_boxes', function (Blueprint $table) {
                $table->dropColumn('observation');
            });
        }
    }
};

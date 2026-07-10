<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customer_order_labelings', 'destination')) {
            Schema::table('customer_order_labelings', function (Blueprint $table) {
                $table->string('destination')->nullable()->after('guide_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_order_labelings', 'destination')) {
            Schema::table('customer_order_labelings', function (Blueprint $table) {
                $table->dropColumn('destination');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_dispatches', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->restrictOnDelete();
            $table->string('idempotency_key', 100)->nullable()->after('dispatch_number')->unique();
            $table->string('destination', 255)->nullable()->after('responsible_user_id');
            $table->string('dispatch_type', 30)->nullable()->after('destination');
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->index(['company_id', 'status'], 'wd_company_status_idx');
        });

        DB::table('warehouse_dispatches')
            ->whereNull('company_id')
            ->update([
                'company_id' => DB::raw('(SELECT customer_purchase_orders.company_id FROM customer_purchase_orders WHERE customer_purchase_orders.id = warehouse_dispatches.customer_purchase_order_id)'),
            ]);

        if (DB::table('warehouse_dispatches')->whereNull('company_id')->exists()) {
            throw new RuntimeException('No se pudo determinar company_id para todos los despachos existentes.');
        }

        DB::table('warehouse_dispatches')
            ->whereNull('dispatch_type')
            ->update(['dispatch_type' => 'customer_order']);

        DB::table('warehouse_dispatches')
            ->where('status', 'registered')
            ->update([
                'status' => 'confirmed',
                'confirmed_at' => DB::raw('COALESCE(confirmed_at, created_at)'),
                'confirmed_by' => DB::raw('COALESCE(confirmed_by, created_by)'),
            ]);

        Schema::table('warehouse_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->string('dispatch_type', 30)->default('customer_order')->nullable(false)->change();
            $table->string('status', 30)->default('draft')->change();
        });
    }

    public function down(): void
    {
        DB::table('warehouse_dispatches')
            ->where('status', 'confirmed')
            ->update(['status' => 'registered']);

        Schema::table('warehouse_dispatches', function (Blueprint $table) {
            $table->string('status', 30)->default('registered')->change();
            $table->dropIndex('wd_company_status_idx');
            $table->dropUnique('warehouse_dispatches_idempotency_key_unique');
            $table->dropForeign(['confirmed_by']);
            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'company_id',
                'idempotency_key',
                'destination',
                'dispatch_type',
                'confirmed_at',
                'confirmed_by',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
            $table->foreignId('deleted_by')->nullable()->after('updated_by');
            $table->text('delete_reason')->nullable()->after('deleted_by');
            $table->softDeletes();

            $table->foreign('deleted_by', 'wecp_deleted_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['warehouse_entry_id', 'deleted_at'], 'wecp_entry_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_entry_credit_payments', function (Blueprint $table) {
            $table->dropIndex('wecp_entry_deleted_idx');
            $table->dropForeign('wecp_deleted_by_fk');
            $table->dropColumn(['deleted_by', 'delete_reason', 'deleted_at']);
        });
    }
};

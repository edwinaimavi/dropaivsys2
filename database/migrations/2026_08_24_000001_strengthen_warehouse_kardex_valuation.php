<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->string('source_key', 190)->nullable()->after('source_item_id');
            $table->unique('source_key', 'wkm_source_key_unique');
        });

        Schema::create('warehouse_kardex_recalculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedInteger('stocks_processed')->default(0);
            $table->unsignedInteger('movements_processed')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'article_id'], 'wkr_scope_index');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_kardex_recalculations');
        Schema::table('warehouse_kardex_movements', function (Blueprint $table) {
            $table->dropUnique('wkm_source_key_unique');
            $table->dropColumn('source_key');
        });
    }
};

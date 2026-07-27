<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->string('seller_type', 20)->nullable()->after('observations');
            $table->foreignId('seller_user_id')->nullable()->after('seller_type')
                ->constrained('users')->nullOnDelete();
            $table->string('seller_dni', 8)->nullable()->after('seller_user_id')->index();
            $table->string('seller_names', 150)->nullable()->after('seller_dni');
            $table->string('seller_lastnames', 150)->nullable()->after('seller_names');
            $table->string('seller_full_name', 255)->nullable()->after('seller_lastnames');
            $table->string('seller_phone', 30)->nullable()->after('seller_full_name');
            $table->string('seller_email', 150)->nullable()->after('seller_phone');
            $table->text('seller_observation')->nullable()->after('seller_email');
        });
    }

    public function down(): void
    {
        Schema::table('customer_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['seller_user_id']);
            $table->dropIndex(['seller_dni']);
            $table->dropColumn([
                'seller_type', 'seller_user_id', 'seller_dni', 'seller_names',
                'seller_lastnames', 'seller_full_name', 'seller_phone',
                'seller_email', 'seller_observation',
            ]);
        });
    }
};

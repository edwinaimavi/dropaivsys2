<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunat_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('sunat_catalog_items', function (Blueprint $table) {
            $table->foreignId('sunat_catalog_id')
                ->nullable()
                ->after('id')
                ->constrained('sunat_catalogs')
                ->nullOnDelete();
            $table->string('source', 50)->nullable()->after('extra_data');
            $table->boolean('is_official')->default(true)->after('source');
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sunat_catalog_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['is_official', 'source']);
            $table->dropConstrainedForeignId('sunat_catalog_id');
        });

        Schema::dropIfExists('sunat_catalogs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | POLYMORPHIC RELATION
            |--------------------------------------------------------------------------
            */

            $table->morphs('documentable');

            /*
            |--------------------------------------------------------------------------
            | DOCUMENT TYPE
            |--------------------------------------------------------------------------
            */

            $table->foreignId('document_type_id')
                ->nullable()
                ->constrained('document_types');

            /*
            |--------------------------------------------------------------------------
            | FILE INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->string('original_name');

            $table->string('stored_name');

            $table->string('file_path');

            $table->string('mime_type', 100)
                ->nullable();

            $table->string('extension', 20)
                ->nullable();

            $table->unsignedBigInteger('file_size')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | DOCUMENT INFORMATION
            |--------------------------------------------------------------------------
            */

            $table->date('issue_date')
                ->nullable();

            $table->date('expiration_date')
                ->nullable();

            $table->text('observation')
                ->nullable();

            $table->enum('status', [
                'ACTIVE',
                'INACTIVE'
            ])->default('ACTIVE');

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('created_by')
                ->nullable();

            $table->unsignedBigInteger('updated_by')
                ->nullable();

            $table->unsignedBigInteger('deleted_by')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index('document_type_id');
            $table->index('status');
            $table->index('expiration_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

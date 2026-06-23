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
        Schema::create('images', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | POLYMORPHIC
            |--------------------------------------------------------------------------
            */
            $table->string('imageable_type');
            $table->unsignedBigInteger('imageable_id');

            /*
            |--------------------------------------------------------------------------
            | FILE
            |--------------------------------------------------------------------------
            */
            $table->string('original_name');
            $table->string('stored_name');

            $table->string('file_path');

            $table->string('mime_type', 100);
            $table->string('extension', 20);

            $table->unsignedBigInteger('file_size')->default(0);

            /*
            |--------------------------------------------------------------------------
            | INFO
            |--------------------------------------------------------------------------
            */
            $table->string('title')->nullable();

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | CONFIG
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_primary')
                ->default(false);

            $table->integer('sort_order')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */
            $table->enum(
                'status',
                [
                    'ACTIVE',
                    'INACTIVE'
                ]
            )->default('ACTIVE');

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users');

            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users');

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index([
                'imageable_type',
                'imageable_id'
            ]);

            $table->index('is_primary');

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
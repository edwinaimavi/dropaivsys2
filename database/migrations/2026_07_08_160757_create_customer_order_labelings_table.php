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
        Schema::create('customer_order_labelings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('customer_purchase_order_id')->constrained('customer_purchase_orders');
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_branch_id')->nullable()->constrained('customer_branches');
            $table->string('invoice_number', 50)->nullable();
            $table->string('guide_number', 50)->nullable();
            $table->unsignedInteger('boxes_count')->default(1);
            $table->decimal('total_quantity', 12, 2)->default(0);
            $table->enum('status', ['DRAFT', 'GENERATED', 'CANCELLED'])->default('DRAFT');
            $table->string('pdf_path')->nullable();
            $table->text('observations')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('status');
            $table->index('customer_purchase_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_order_labelings');
    }
};

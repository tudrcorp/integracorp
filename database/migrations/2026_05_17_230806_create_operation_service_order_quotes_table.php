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
        Schema::create('operation_service_order_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_service_order_id')->constrained('operation_service_orders')->cascadeOnDelete();
            $table->string('quote_number');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_external')->nullable();
            $table->decimal('bcv_rate', 12, 4)->nullable();
            $table->decimal('total_amount_usd', 14, 4)->default(0);
            $table->decimal('total_amount_ves', 14, 4)->default(0);
            $table->json('items_payload')->nullable();
            $table->string('quote_pdf_path')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_service_order_quotes');
    }
};

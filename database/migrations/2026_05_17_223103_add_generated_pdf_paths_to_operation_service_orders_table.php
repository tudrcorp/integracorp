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
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->string('service_order_pdf_path')->nullable();
            $table->string('associated_quote_pdf_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->dropColumn([
                'service_order_pdf_path',
                'associated_quote_pdf_path',
            ]);
        });
    }
};

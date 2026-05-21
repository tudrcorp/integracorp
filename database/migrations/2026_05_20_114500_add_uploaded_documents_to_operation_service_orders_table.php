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
            $table->json('uploaded_documents')->nullable()->after('associated_quote_pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->dropColumn('uploaded_documents');
        });
    }
};

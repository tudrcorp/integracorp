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
        Schema::table('operation_coordination_services', function (Blueprint $table) {
            $table->json('uploaded_documents')->nullable()->after('managed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_coordination_services', function (Blueprint $table) {
            $table->dropColumn('uploaded_documents');
        });
    }
};

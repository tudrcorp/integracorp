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
        Schema::table('operation_quote_generators', function (Blueprint $table) {
            $table->string('status')->default('PENDIENTE POR APROBAR')->after('type_service');
            $table->unsignedBigInteger('operation_service_order_id')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_quote_generators', function (Blueprint $table) {
            $table->dropColumn(['status', 'operation_service_order_id']);
        });
    }
};

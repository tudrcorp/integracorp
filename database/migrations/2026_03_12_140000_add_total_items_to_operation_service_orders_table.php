<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->unsignedInteger('total_items')->default(0)->after('observations');
            $table->unsignedInteger('total_items_unit')->default(0)->after('total_items');
        });
    }

    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->dropColumn(['total_items', 'total_items_unit']);
        });
    }
};

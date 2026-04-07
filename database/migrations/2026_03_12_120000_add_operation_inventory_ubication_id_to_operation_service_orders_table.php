<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->foreignId('operation_inventory_ubication_id')
                ->nullable()
                ->after('supplier_external')
                ->constrained('operation_inventory_ubications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table) {
            $table->dropForeign(['operation_inventory_ubication_id']);
        });
    }
};

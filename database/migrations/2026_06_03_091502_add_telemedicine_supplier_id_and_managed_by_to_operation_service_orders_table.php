<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_service_orders', 'managed_by')) {
                $table->string('managed_by')->nullable()->after('status');
            }

            if (! Schema::hasColumn('operation_service_orders', 'telemedicine_supplier_id')) {
                $table->foreignId('telemedicine_supplier_id')
                    ->nullable()
                    ->after('managed_by')
                    ->constrained('suppliers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_service_orders', 'telemedicine_supplier_id')) {
                $table->dropConstrainedForeignId('telemedicine_supplier_id');
            }

            if (Schema::hasColumn('operation_service_orders', 'managed_by')) {
                $table->dropColumn('managed_by');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operation_inventory_outflows')) {
            return;
        }

        Schema::table('operation_inventory_outflows', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_inventory_outflows', 'telemedicine_case_id')) {
                $table->unsignedBigInteger('telemedicine_case_id')->nullable()->after('operation_inventory_id');
                $table->index('telemedicine_case_id');
            }
        });

        if (
            Schema::hasColumn('operation_inventory_outflows', 'telemedicine_case_id')
            && Schema::hasTable('operation_inventory_movements')
            && Schema::getConnection()->getDriverName() === 'mysql'
        ) {
            DB::statement(<<<'SQL'
                UPDATE operation_inventory_outflows AS outflows
                INNER JOIN operation_inventory_movements AS movements
                    ON movements.operation_inventory_id = outflows.operation_inventory_id
                    AND movements.quantity = outflows.quantity
                    AND movements.type = 'SALIDA TELEMEDICINA'
                    AND movements.telemedicine_case_id IS NOT NULL
                    AND ABS(TIMESTAMPDIFF(SECOND, movements.created_at, outflows.created_at)) <= 5
                SET outflows.telemedicine_case_id = movements.telemedicine_case_id
                WHERE outflows.telemedicine_case_id IS NULL
                  AND outflows.type_entry = 'SALIDA TELEMEDICINA'
            SQL);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_inventory_outflows')) {
            return;
        }

        Schema::table('operation_inventory_outflows', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_inventory_outflows', 'telemedicine_case_id')) {
                $table->dropIndex(['telemedicine_case_id']);
                $table->dropColumn('telemedicine_case_id');
            }
        });
    }
};

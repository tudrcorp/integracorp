<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El inventario de medicamentos se descuenta al entregar (finalizar la orden de servicio),
 * no al recetar. Cada medicamento recetado lleva la marca de cuándo salió del inventario,
 * para no descontarlo dos veces; y cada ítem de la orden, el vínculo al medicamento y al
 * producto que representa, para descontar exactamente ese producto.
 *
 * Los medicamentos que ya tuvieron salida en la consulta (regla anterior) se marcan aquí
 * desde su movimiento «SALIDA TELEMEDICINA», así no se descuentan de nuevo al entregarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('telemedicine_patient_medications', 'inventory_dispatched_at')) {
            Schema::table('telemedicine_patient_medications', function (Blueprint $table): void {
                $table->timestamp('inventory_dispatched_at')->nullable()->after('operation_inventory_id');
                $table->string('inventory_dispatch_source', 30)->nullable()->after('inventory_dispatched_at');
                $table->unsignedBigInteger('inventory_dispatch_order_id')->nullable()->after('inventory_dispatch_source');
                $table->index('inventory_dispatch_order_id', 'tpm_inventory_dispatch_order_idx');
            });
        }

        if (! Schema::hasColumn('operation_service_order_items', 'telemedicine_patient_medication_id')) {
            Schema::table('operation_service_order_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('telemedicine_patient_medication_id')->nullable()->after('operation_service_order_id');
                $table->unsignedBigInteger('operation_inventory_id')->nullable()->after('telemedicine_patient_medication_id');
                $table->index('telemedicine_patient_medication_id', 'osoi_medication_idx');
            });
        }

        DB::table('telemedicine_patient_medications as m')
            ->join('operation_inventory_movements as v', function ($join): void {
                $join->on('v.telemedicine_consultation_id', '=', 'm.telemedicine_consultation_patient_id')
                    ->on('v.operation_inventory_id', '=', 'm.operation_inventory_id');
            })
            ->where('v.type', 'SALIDA TELEMEDICINA')
            ->whereNotNull('m.operation_inventory_id')
            ->whereNull('m.inventory_dispatched_at')
            ->update([
                'm.inventory_dispatched_at' => DB::raw('v.created_at'),
                'm.inventory_dispatch_source' => 'CONSULTA',
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('operation_service_order_items', 'telemedicine_patient_medication_id')) {
            Schema::table('operation_service_order_items', function (Blueprint $table): void {
                $table->dropIndex('osoi_medication_idx');
                $table->dropColumn(['telemedicine_patient_medication_id', 'operation_inventory_id']);
            });
        }

        if (Schema::hasColumn('telemedicine_patient_medications', 'inventory_dispatched_at')) {
            Schema::table('telemedicine_patient_medications', function (Blueprint $table): void {
                $table->dropIndex('tpm_inventory_dispatch_order_idx');
                $table->dropColumn(['inventory_dispatched_at', 'inventory_dispatch_source', 'inventory_dispatch_order_id']);
            });
        }
    }
};

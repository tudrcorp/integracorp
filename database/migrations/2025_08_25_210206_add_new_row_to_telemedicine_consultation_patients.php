<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas ortopédicas adicionales (no presentes en el esquema base de producción).
     */
    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_consultation_patients')) {
            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table) {
            $orthopedicColumns = [
                'hombro_izq',
                'hombro_der',
                'hombro_comp',
                'codo_izq',
                'codo_der',
                'codo_comp',
                'muneca_izq',
                'muneca_der',
                'muneca_comp',
                'mano_izq',
                'mano_der',
                'mano_comp',
                'humero_izq',
                'humero_der',
                'humero_comp',
                'ante_izq',
                'ante_der',
                'ante_comp',
                'cdl_ap',
                'pocep',
                'cc_ap',
                'cc_oblicuas',
                'cc_la_flexion',
                'cc_la_extension',
                'cls_ap',
                'cls_oblicuas',
                'cls_la_flexion',
                'cls_la_extension',
            ];

            foreach ($orthopedicColumns as $column) {
                if (! Schema::hasColumn('telemedicine_consultation_patients', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_consultation_patients')) {
            return;
        }

        Schema::table('telemedicine_consultation_patients', function (Blueprint $table) {
            $orthopedicColumns = [
                'hombro_izq',
                'hombro_der',
                'hombro_comp',
                'codo_izq',
                'codo_der',
                'codo_comp',
                'muneca_izq',
                'muneca_der',
                'muneca_comp',
                'mano_izq',
                'mano_der',
                'mano_comp',
                'humero_izq',
                'humero_der',
                'humero_comp',
                'ante_izq',
                'ante_der',
                'ante_comp',
                'cdl_ap',
                'pocep',
                'cc_ap',
                'cc_oblicuas',
                'cc_la_flexion',
                'cc_la_extension',
                'cls_ap',
                'cls_oblicuas',
                'cls_la_flexion',
                'cls_la_extension',
            ];

            foreach ($orthopedicColumns as $column) {
                if (Schema::hasColumn('telemedicine_consultation_patients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

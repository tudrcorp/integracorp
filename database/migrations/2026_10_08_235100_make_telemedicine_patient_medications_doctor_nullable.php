<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un medicamento del registro RETAIL lo indica el equipo médico asignado al caso
 * (o nadie, si el retail no incluye telemedicina ni AMD): no hay médico particular.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('telemedicine_patient_medications', 'telemedicine_doctor_id')) {
            Schema::table('telemedicine_patient_medications', function (Blueprint $table): void {
                $table->integer('telemedicine_doctor_id')->nullable()->change();
            });
        }
    }

    /**
     * Solo vuelve a NOT NULL si no hay filas sin médico: revertir no puede perder datos.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('telemedicine_patient_medications', 'telemedicine_doctor_id')) {
            return;
        }

        if (DB::table('telemedicine_patient_medications')->whereNull('telemedicine_doctor_id')->exists()) {
            return;
        }

        Schema::table('telemedicine_patient_medications', function (Blueprint $table): void {
            $table->integer('telemedicine_doctor_id')->nullable(false)->change();
        });
    }
};

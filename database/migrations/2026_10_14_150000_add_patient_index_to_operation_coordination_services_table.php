<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice de «Servicios del paciente» (Operaciones): la consulta filtra por
 * paciente y ordena por fecha de registro. También lo aprovechan el reporte de
 * siniestralidad y las opciones de filtro de esa página.
 */
return new class extends Migration
{
    private const TABLE = 'operation_coordination_services';

    private const INDEX = 'ocs_patient_created_at_index';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['telemedicine_patient_id', 'created_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasIndex(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};

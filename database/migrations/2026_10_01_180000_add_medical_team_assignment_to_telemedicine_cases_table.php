<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de caso al «Equipo Médico» de guardia en lugar de a un médico.
 *
 * El caso nace sin médico y lo toma quien esté de guardia; desde la primera
 * consulta `telemedicine_doctor_id` sigue al médico que actualiza. Por eso el
 * equipo no puede deducirse del médico: queda fijado aquí. Un equipo con
 * proveedor vacío es el equipo TDG. No se reutiliza `supplier_id`, que guarda
 * el proveedor del paciente y alimenta los filtros de Operaciones.
 */
return new class extends Migration
{
    private const TABLE = 'telemedicine_cases';

    private const INDEX = 'telemedicine_cases_medical_team_index';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'assigned_to_medical_team')) {
                $table->boolean('assigned_to_medical_team')->default(false)->after('telemedicine_doctor_id');
            }

            if (! Schema::hasColumn(self::TABLE, 'medical_team_supplier_id')) {
                $table->unsignedBigInteger('medical_team_supplier_id')->nullable()->after('assigned_to_medical_team');
            }
        });

        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->index(['assigned_to_medical_team', 'medical_team_supplier_id'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (['medical_team_supplier_id', 'assigned_to_medical_team'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

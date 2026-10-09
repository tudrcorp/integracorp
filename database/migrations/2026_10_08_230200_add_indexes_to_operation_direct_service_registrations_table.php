<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de `operation_direct_service_registrations`.
 *
 * La versión original de 2026_10_08_230000 los pedía con nombre automático,
 * que pasa de 64 caracteres: en MySQL la tabla quedó creada sin ningún índice.
 * Donde esa migración ya quedó registrada, esta los crea con nombre corto; si
 * ya existen no hace nada.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> columna => índice
     */
    private const INDEXES = [
        'telemedicine_patient_id' => 'odsr_patient_idx',
        'telemedicine_case_id' => 'odsr_case_idx',
        'registered_by_user_id' => 'odsr_registered_by_idx',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('operation_direct_service_registrations')) {
            return;
        }

        foreach (self::INDEXES as $column => $index) {
            if (Schema::hasColumn('operation_direct_service_registrations', $column)
                && ! Schema::hasIndex('operation_direct_service_registrations', $index)) {
                Schema::table('operation_direct_service_registrations', function (Blueprint $table) use ($column, $index): void {
                    $table->index($column, $index);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_direct_service_registrations')) {
            return;
        }

        foreach (self::INDEXES as $index) {
            if (Schema::hasIndex('operation_direct_service_registrations', $index)) {
                Schema::table('operation_direct_service_registrations', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }
    }
};

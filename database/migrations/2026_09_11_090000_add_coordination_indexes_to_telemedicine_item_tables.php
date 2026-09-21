<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las cuatro tablas de ítems clínicos.
 *
 * El cuadro de control de servicios médicos filtra por coordinación y estatus en
 * ocho subconsultas correlacionadas, y además consulta estas tablas una vez por
 * fila. Ninguna tenía más índice que la clave primaria, así que cada una de esas
 * consultas recorría la tabla entera (EXPLAIN: type=ALL, key=NULL).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'telemedicine_patient_medications',
        'telemedicine_patient_labs',
        'telemedicine_patient_studies',
        'telemedicine_patient_specialties',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->addIndex($table, $table.'_coordination_status_index', ['operation_coordination_service_id', 'status']);
            $this->addIndex($table, $table.'_case_index', ['telemedicine_case_id']);
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, string $name, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns): void {
            $blueprint->index($columns, $name);
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ([$table.'_coordination_status_index', $table.'_case_index'] as $name) {
                if (! Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }
};

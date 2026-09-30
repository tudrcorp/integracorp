<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices del cuadro de control de servicios médicos.
 *
 * `operation_coordination_services` sólo tenía la clave primaria y
 * `reference_number`: la agrupación por caso, las pestañas por estatus, el orden
 * por fecha de solicitud y el alcance por proveedor recorrían la tabla entera.
 * Las cotizaciones y los ítems de orden se precargan por coordinación y por
 * orden, columnas que tampoco estaban indexadas.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private const INDEXES = [
        'operation_coordination_services' => [
            'operation_coordination_services_case_index' => ['telemedicine_case_id'],
            'operation_coordination_services_status_solicitud_index' => ['status', 'date_solicitud'],
            'operation_coordination_services_supplier_index' => ['supplier_id'],
        ],
        'operation_quote_generators' => [
            'operation_quote_generators_coordination_index' => ['operation_coordination_service_id'],
        ],
        'operation_service_order_items' => [
            'operation_service_order_items_order_index' => ['operation_service_order_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                $this->addIndex($table, $name, $columns);
            }
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

        if (Schema::hasIndex($table, $name) || Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns): void {
            $blueprint->index($columns, $name);
        });
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
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

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para el listado de órdenes de servicio.
 *
 * La tabla se filtra y ordena por estatus, fechas y coordinación, y el barrido
 * de caducidad recorre `status` + `approved_at` / `created_at`. Ninguna de esas
 * columnas tenía índice: con pocas filas no se nota, pero el escaneo completo
 * crece linealmente con el histórico.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private const INDEXES = [
        'operation_service_orders_status_index' => ['status'],
        'operation_service_orders_administrative_status_index' => ['administrative_status'],
        'operation_service_orders_created_at_index' => ['created_at'],
        'operation_service_orders_status_approved_at_index' => ['status', 'approved_at'],
        'operation_service_orders_coordination_index' => ['operation_coordination_service_id'],
        'operation_service_orders_supplier_id_index' => ['supplier_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('operation_service_orders')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn('operation_service_orders', $column)) {
                    continue 2;
                }
            }

            if (Schema::hasIndex('operation_service_orders', $name)) {
                continue;
            }

            Schema::table('operation_service_orders', function (Blueprint $table) use ($name, $columns): void {
                $table->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_service_orders')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (! Schema::hasIndex('operation_service_orders', $name)) {
                continue;
            }

            Schema::table('operation_service_orders', function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        }
    }
};

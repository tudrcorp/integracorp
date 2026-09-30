<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices del cuadro de control de servicios médicos (Operaciones).
 *
 * La tabla solo tenía la clave primaria y `reference_number`: el filtro del
 * proveedor (`supplier_id`), el de ATENMEDI (`managed_by`), las pestañas por
 * estatus y las búsquedas por caso recorrían la tabla completa.
 */
return new class extends Migration
{
    private const TABLE = 'operation_coordination_services';

    /**
     * @var array<string, string>
     */
    private const INDEXES = [
        'ocs_supplier_id_index' => 'supplier_id',
        'ocs_telemedicine_case_id_index' => 'telemedicine_case_id',
        'ocs_status_index' => 'status',
        'ocs_managed_by_index' => 'managed_by',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $column) {
            if (Schema::hasColumn(self::TABLE, $column) && ! Schema::hasIndex(self::TABLE, $name)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column, $name): void {
                    $table->index($column, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex(self::TABLE, $name)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice de `operation_coordination_services.direct_service_registration_id`.
 *
 * La migración 2026_10_08_230000 lo creaba con el nombre automático, que pasa
 * de 64 caracteres (límite de MySQL): donde ya corrió quedó la columna sin
 * índice. Esta lo crea con nombre corto y no hace nada si ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('operation_coordination_services', 'direct_service_registration_id')
            && ! Schema::hasIndex('operation_coordination_services', 'ocs_direct_registration_idx')) {
            Schema::table('operation_coordination_services', function (Blueprint $table): void {
                $table->index('direct_service_registration_id', 'ocs_direct_registration_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('operation_coordination_services', 'ocs_direct_registration_idx')) {
            Schema::table('operation_coordination_services', function (Blueprint $table): void {
                $table->dropIndex('ocs_direct_registration_idx');
            });
        }
    }
};

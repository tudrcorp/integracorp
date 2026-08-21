<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deja constancia de qué plan del catálogo alimentó la matriz de una cotización.
 *
 * Es solo trazabilidad: la cotización sigue guardando su propia copia de
 * columnas, beneficios, límites y tarifas en las tablas `plan_generator_*`.
 * Editar el plan después no altera una cotización ya emitida, igual que la neta
 * congelada del reporte de empresas aliadas.
 *
 * Nullable a propósito: las cotizaciones armadas a mano —las que ya existen y
 * cualquiera futura— no tienen plan de origen y son perfectamente válidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_generators', 'plan_id')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->unsignedBigInteger('plan_id')->nullable()->after('name');
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plan_generators', 'plan_id')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropIndex(['plan_id']);
            $table->dropColumn('plan_id');
        });
    }
};

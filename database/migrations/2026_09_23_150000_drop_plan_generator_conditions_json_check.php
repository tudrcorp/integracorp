<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorConditionsColumn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * MariaDB implementa JSON como LONGTEXT con CHECK (json_valid()).
 * La migración anterior veía el tipo longtext y no tocaba esa regla, así que
 * en producción el texto de las condiciones seguía siendo rechazado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_generators') || ! Schema::hasColumn('plan_generators', 'conditions')) {
            return;
        }

        PlanGeneratorConditionsColumn::dropJsonValidityConstraint();
    }

    public function down(): void
    {
        // No se restaura el CHECK: volver a exigir JSON rechazaría el texto pegado.
    }
};

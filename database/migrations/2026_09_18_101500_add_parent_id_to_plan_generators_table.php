<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuelga una cotización derivada del registro base que le sirvió de plantilla.
 *
 * La familia es de un solo nivel: si el analista deriva de una derivada, la
 * nueva se cuelga del mismo registro base, nunca de su hermana. Eso mantiene la
 * agrupación de la tabla en dos niveles y evita cadenas imposibles de leer.
 *
 * Sin llave foránea a propósito. Borrar un registro base no debe arrastrar
 * cotizaciones derivadas que pueden estar aprobadas o ya en pre-afiliación:
 * `PlanGenerator::deleting` reasigna los hijos a base antes del borrado, y una
 * FK con cascade convertiría un error de tabla en pérdida de documentos
 * comerciales. Tampoco `nullOnDelete`, porque las ~397 migraciones de este
 * repo no se ejecutan en orden y el esquema vino de un dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_generators', 'parent_id')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plan_generators', 'parent_id')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};

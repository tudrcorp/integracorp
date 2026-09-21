<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca con qué formulario se armó el plan.
 *
 * 1 = planes históricos, que se siguen editando con el formulario anterior.
 * 2 = planes creados con el asistente de armado (coberturas -> beneficios ->
 *     límites -> rangos y tarifas).
 *
 * Existe para que abrir un plan viejo y guardarlo no reescriba su estructura
 * con las reglas del asistente: los 18 planes ya cargados alimentan
 * cotizaciones, afiliaciones y PDFs emitidos, y no se migran.
 *
 * Es distinto de `pricing_mode`, que dice *cómo cobra* el plan y está poblado
 * para todos. Este dice *con qué formulario se editará*.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'structure_version')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('structure_version')
                ->default(1)
                ->after('pricing_mode');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plans', 'structure_version')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('structure_version');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza la matriz de un plan generado con el plan del catálogo que la
 * materializa.
 *
 * Al cerrar la pre-afiliación, el plan generado se publica en `plans` (tipo
 * DRESS-TAILOR, como los demás planes a la medida) con sus coberturas, rangos
 * de edad, tarifas y beneficios. Sin estos enlaces, la afiliación quedaba con
 * `plan_id`/`coverage_id`/`age_range_id` en cero y las columnas Plan, Cobertura
 * y Rango de Edad salían vacías en «Plan(es) Afiliado(s)».
 *
 * Guardar la correspondencia —y no recalcularla por precio o por orden— es lo
 * que permite republicar el mismo plan generado cuando su matriz cambia sin
 * duplicar coberturas ni dejar tarifas colgadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plan_generators', 'catalog_plan_id')) {
            Schema::table('plan_generators', function (Blueprint $table): void {
                $table->unsignedBigInteger('catalog_plan_id')->nullable()->after('plan_id');
                $table->index('catalog_plan_id');
            });
        }

        if (! Schema::hasColumn('plan_generator_columns', 'coverage_id')) {
            Schema::table('plan_generator_columns', function (Blueprint $table): void {
                $table->unsignedBigInteger('coverage_id')->nullable()->after('rate_adjustment_percent');
                $table->index('coverage_id');
            });
        }

        if (! Schema::hasColumn('plan_generator_rate_rows', 'age_range_id')) {
            Schema::table('plan_generator_rate_rows', function (Blueprint $table): void {
                $table->unsignedBigInteger('age_range_id')->nullable()->after('age_range_label');
                $table->index('age_range_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plan_generator_rate_rows', 'age_range_id')) {
            Schema::table('plan_generator_rate_rows', function (Blueprint $table): void {
                $table->dropIndex(['age_range_id']);
                $table->dropColumn('age_range_id');
            });
        }

        if (Schema::hasColumn('plan_generator_columns', 'coverage_id')) {
            Schema::table('plan_generator_columns', function (Blueprint $table): void {
                $table->dropIndex(['coverage_id']);
                $table->dropColumn('coverage_id');
            });
        }

        if (Schema::hasColumn('plan_generators', 'catalog_plan_id')) {
            Schema::table('plan_generators', function (Blueprint $table): void {
                $table->dropIndex(['catalog_plan_id']);
                $table->dropColumn('catalog_plan_id');
            });
        }
    }
};

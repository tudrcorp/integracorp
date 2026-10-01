<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobertura en la que el analista ubica a cada persona del padrón de una
 * pre-afiliación corporativa del generador de planes.
 *
 * `column_key` es la clave de la columna de la matriz (la cobertura), no un
 * `coverage_id`: el catálogo de coberturas recién se publica al crear la
 * afiliación. El rango etario y la tarifa no se guardan a propósito: se
 * resuelven siempre contra la matriz vigente, para que una edición del plan
 * no deje una tarifa congelada que ya no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_generator_populations')) {
            return;
        }

        Schema::table('plan_generator_populations', function (Blueprint $table): void {
            if (! Schema::hasColumn('plan_generator_populations', 'column_key')) {
                $table->string('column_key', 64)->nullable()->after('import_id');
            }

            if (! Schema::hasColumn('plan_generator_populations', 'coverage_assigned_by')) {
                $table->string('coverage_assigned_by')->nullable()->after('column_key');
            }

            if (! Schema::hasColumn('plan_generator_populations', 'coverage_assigned_at')) {
                $table->timestamp('coverage_assigned_at')->nullable()->after('coverage_assigned_by');
            }
        });

        if (! Schema::hasIndex('plan_generator_populations', 'pg_populations_plan_column_key_index')) {
            Schema::table('plan_generator_populations', function (Blueprint $table): void {
                $table->index(['plan_generator_id', 'column_key'], 'pg_populations_plan_column_key_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('plan_generator_populations')) {
            return;
        }

        if (Schema::hasIndex('plan_generator_populations', 'pg_populations_plan_column_key_index')) {
            Schema::table('plan_generator_populations', function (Blueprint $table): void {
                $table->dropIndex('pg_populations_plan_column_key_index');
            });
        }

        Schema::table('plan_generator_populations', function (Blueprint $table): void {
            foreach (['coverage_assigned_at', 'coverage_assigned_by', 'column_key'] as $column) {
                if (Schema::hasColumn('plan_generator_populations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para resolver el plan de una cotización corporativa (su detalle) y los planes de la
 * afiliación corporativa que salió de ella, en el análisis de cotizaciones de Métricas.
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: list<string>, 2: string}> */
    private array $indexes = [
        ['detail_corporate_quotes', ['corporate_quote_id', 'plan_id'], 'dcq_quote_plan_idx'],
        ['afilliation_corporate_plans', ['affiliation_corporate_id', 'plan_id'], 'acp_affiliation_plan_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, , $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }
};

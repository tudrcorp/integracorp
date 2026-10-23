<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para el Informe administrativo: ventas por fecha y por alcance comercial, y
 * bitácoras de estatus por afiliación y fecha. Idempotente: solo crea lo que falta.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private const INDEXES = [
        'sales' => [
            'sales_created_at_index' => ['created_at'],
            'sales_code_agency_created_at_index' => ['code_agency', 'created_at'],
            'sales_agent_id_created_at_index' => ['agent_id', 'created_at'],
            'sales_owner_code_index' => ['owner_code'],
        ],
        'status_log_affiliations' => [
            'status_log_affiliations_affiliation_created_index' => ['affiliation_id', 'created_at'],
            'status_log_affiliations_created_at_index' => ['created_at'],
        ],
        'status_log_affiliation_corporates' => [
            'status_log_aff_corporates_affiliation_created_index' => ['affiliation_corporate_id', 'created_at'],
            'status_log_aff_corporates_created_at_index' => ['created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name) || array_diff($columns, Schema::getColumnListing($table)) !== []) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eliminación lógica de casos de telemedicina.
 *
 * El caso nunca se borra: pasa a `status = ELIMINADO` y guarda aquí el estatus
 * previo, el motivo y quién lo eliminó, para poder restaurarlo y para que la
 * auditoría cuadre. El índice de `status` sostiene el scope global que oculta
 * los casos eliminados y sus trazas en todo el sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_cases')) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table): void {
            if (! Schema::hasColumn('telemedicine_cases', 'deletion_status_before')) {
                $table->string('deletion_status_before', 100)->nullable()->after('status');
            }

            if (! Schema::hasColumn('telemedicine_cases', 'deletion_reason')) {
                $table->text('deletion_reason')->nullable()->after('deletion_status_before');
            }

            if (! Schema::hasColumn('telemedicine_cases', 'deleted_by_user_id')) {
                $table->unsignedBigInteger('deleted_by_user_id')->nullable()->after('deletion_reason');
            }

            if (! Schema::hasColumn('telemedicine_cases', 'deleted_by_name')) {
                $table->string('deleted_by_name')->nullable()->after('deleted_by_user_id');
            }

            if (! Schema::hasColumn('telemedicine_cases', 'logically_deleted_at')) {
                $table->timestamp('logically_deleted_at')->nullable()->after('deleted_by_name');
            }
        });

        $this->createIndexIfMissing('telemedicine_cases_status_index', function (Blueprint $table): void {
            $table->index('status', 'telemedicine_cases_status_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_cases')) {
            return;
        }

        $this->dropIndexIfExists('telemedicine_cases_status_index');

        Schema::table('telemedicine_cases', function (Blueprint $table): void {
            foreach ([
                'deletion_status_before',
                'deletion_reason',
                'deleted_by_user_id',
                'deleted_by_name',
                'logically_deleted_at',
            ] as $column) {
                if (Schema::hasColumn('telemedicine_cases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function createIndexIfMissing(string $indexName, callable $definition): void
    {
        if ($this->indexExists($indexName)) {
            return;
        }

        Schema::table('telemedicine_cases', $definition);
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! $this->indexExists($indexName)) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $indexName): bool
    {
        return collect(Schema::getIndexes('telemedicine_cases'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};

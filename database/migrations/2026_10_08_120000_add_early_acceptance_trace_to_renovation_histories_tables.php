<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traza de la renovación anticipada (aceptada antes del período de renovación):
 * quién la autorizó, con qué motivo y cuántos días faltaban para la fecha de
 * renovación. Aditiva e idempotente: las filas existentes quedan como no anticipadas.
 *
 * El índice lleva nombre corto explícito: el automático de la tabla corporativa
 * pasa de los 64 caracteres que admite MySQL.
 */
return new class extends Migration
{
    /** @var array<string, string> Tabla => nombre del índice. */
    private const TABLES = [
        'affiliation_renovation_histories' => 'arh_is_early_acceptance_idx',
        'affiliation_corporate_renovation_histories' => 'acrh_is_early_acceptance_idx',
    ];

    /** @var list<string> */
    private const COLUMNS = ['is_early_acceptance', 'days_before_renewal_at_accept', 'early_acceptance_reason', 'accepted_by_user_id'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'is_early_acceptance')) {
                    $table->boolean('is_early_acceptance')->default(false)->after('status_at_accept');
                }

                if (! Schema::hasColumn($tableName, 'days_before_renewal_at_accept')) {
                    $table->integer('days_before_renewal_at_accept')->nullable()->after('is_early_acceptance');
                }

                if (! Schema::hasColumn($tableName, 'early_acceptance_reason')) {
                    $table->text('early_acceptance_reason')->nullable()->after('days_before_renewal_at_accept');
                }

                if (! Schema::hasColumn($tableName, 'accepted_by_user_id')) {
                    $table->unsignedBigInteger('accepted_by_user_id')->nullable()->after('accepted_by');
                }
            });

            /** Una corrida previa pudo dejar el índice con el nombre automático. */
            $legacyIndex = $tableName.'_is_early_acceptance_index';

            if (! Schema::hasIndex($tableName, $indexName) && ! Schema::hasIndex($tableName, $legacyIndex)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->index('is_early_acceptance', $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ([$indexName, $tableName.'_is_early_acceptance_index'] as $index) {
                if (Schema::hasIndex($tableName, $index)) {
                    Schema::table($tableName, function (Blueprint $table) use ($index): void {
                        $table->dropIndex($index);
                    });
                }
            }

            $existing = array_values(array_filter(self::COLUMNS, fn (string $column): bool => Schema::hasColumn($tableName, $column)));

            if ($existing !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($existing): void {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};

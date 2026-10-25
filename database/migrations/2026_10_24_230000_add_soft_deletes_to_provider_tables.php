<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eliminación reversible de proveedores jurídicos, naturales y aliados
 * corporativos. El proveedor no se borra: `deleted_at` lo oculta en todo el
 * sistema y se guarda quién lo eliminó y por qué, para restaurarlo. Aditiva e
 * idempotente.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> tabla => prefijo del índice
     */
    private const TABLES = [
        'suppliers' => 'suppliers',
        'doctor_nurses' => 'doctor_nurses',
        'corporate_allies' => 'corporate_allies',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $prefix) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->softDeletes();
                }

                if (! Schema::hasColumn($table, 'deleted_by_user_id')) {
                    $blueprint->unsignedBigInteger('deleted_by_user_id')->nullable();
                }

                if (! Schema::hasColumn($table, 'deleted_by_name')) {
                    $blueprint->string('deleted_by_name')->nullable();
                }

                if (! Schema::hasColumn($table, 'deletion_reason')) {
                    $blueprint->text('deletion_reason')->nullable();
                }
            });

            if (! $this->indexExists($table, $prefix.'_deleted_at_index')) {
                Schema::table($table, function (Blueprint $blueprint) use ($prefix): void {
                    $blueprint->index('deleted_at', $prefix.'_deleted_at_index');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $prefix) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($this->indexExists($table, $prefix.'_deleted_at_index')) {
                Schema::table($table, function (Blueprint $blueprint) use ($prefix): void {
                    $blueprint->dropIndex($prefix.'_deleted_at_index');
                });
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['deletion_reason', 'deleted_by_name', 'deleted_by_user_id', 'deleted_at'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return collect(Schema::getIndexes($table))->contains(fn (array $definition): bool => $definition['name'] === $index);
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};

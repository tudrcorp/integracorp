<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\DataChangeAudit;
use App\Observers\DataChangeAuditObserver;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cambios masivos auditados: hace exactamente el mismo `update` o `delete` que el código
 * original (mismo resultado, mismo conteo) y registra en la auditoría el antes y el después
 * de cada fila afectada. Los cambios por query builder no disparan observers: sin esto
 * quedarían invisibles.
 *
 * Si la auditoría falla, el cambio sigue (queda en el log), igual que en el observer.
 */
final class DataChangeAuditor
{
    private const CHUNK = 500;

    /**
     * @param  array<string, mixed>  $values
     */
    public static function update(string $entity, EloquentBuilder|QueryBuilder $query, array $values): int
    {
        $base = $query instanceof EloquentBuilder ? $query->toBase() : $query;
        $columns = array_values(array_filter(array_keys($values), fn (string $column): bool => $column !== 'updated_at'));
        $before = self::snapshot($entity, $base, $columns);
        $affected = $query->update($values);

        if ($before === [] || $columns === []) {
            return $affected;
        }

        try {
            $connection = $base->getConnection();
            $after = [];

            foreach (array_chunk(array_keys($before), self::CHUNK) as $ids) {
                foreach ($connection->table((string) $base->from)->whereIn('id', $ids)->get(['id', ...$columns]) as $row) {
                    $after[(int) $row->id] = (array) $row;
                }
            }

            foreach ($before as $id => $row) {
                $changes = [];

                foreach ($columns as $column) {
                    $new = $after[$id][$column] ?? ($values[$column] instanceof Expression ? null : $values[$column]);
                    $changes[$column] = ['old' => $row['values'][$column] ?? null, 'new' => $new];
                }

                DataChangeAuditObserver::write($entity, $id, $row['label'], null, DataChangeAudit::EVENT_UPDATED, $changes, $connection->getName());
            }
        } catch (Throwable $exception) {
            Log::warning('DataChangeAuditor: no se registró el cambio masivo', ['entity' => $entity, 'error' => $exception->getMessage()]);
        }

        return $affected;
    }

    public static function delete(string $entity, EloquentBuilder|QueryBuilder $query): int
    {
        $base = $query instanceof EloquentBuilder ? $query->toBase() : $query;
        $before = self::snapshot($entity, $base, null);
        $affected = $query->delete();

        try {
            foreach ($before as $id => $row) {
                $changes = [];

                foreach ($row['values'] as $column => $value) {
                    if ($value !== null && $value !== '') {
                        $changes[$column] = ['old' => $value, 'new' => null];
                    }
                }

                DataChangeAuditObserver::write($entity, $id, $row['label'], null, DataChangeAudit::EVENT_DELETED, $changes, $base->getConnection()->getName());
            }
        } catch (Throwable $exception) {
            Log::warning('DataChangeAuditor: no se registró la eliminación masiva', ['entity' => $entity, 'error' => $exception->getMessage()]);
        }

        return $affected;
    }

    /**
     * Valores actuales de las filas que el cambio va a tocar (todas las columnas si `$columns`
     * es null, para la foto de una eliminación).
     *
     * @param  list<string>|null  $columns
     * @return array<int, array{label: ?string, values: array<string, mixed>}>
     */
    private static function snapshot(string $entity, QueryBuilder $base, ?array $columns): array
    {
        try {
            $class = DataChangeAuditRegistry::classFor($entity);
            $codeFields = $class !== null ? DataChangeAuditRegistry::entities()[$class]['code'] : [];
            $select = $columns === null ? ['*'] : array_values(array_unique(['id', ...$columns, ...$codeFields]));
            $rows = [];

            foreach ((clone $base)->get($select) as $row) {
                $row = (array) $row;
                $label = trim(implode(' · ', array_filter(array_map(fn (string $field): string => trim((string) ($row[$field] ?? '')), $codeFields))));
                $values = $columns === null ? $row : array_intersect_key($row, array_flip($columns));

                $rows[(int) $row['id']] = ['label' => $label !== '' ? $label : null, 'values' => $values];
            }

            return $rows;
        } catch (Throwable $exception) {
            Log::warning('DataChangeAuditor: no se leyó el estado previo', ['entity' => $entity, 'error' => $exception->getMessage()]);

            return [];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DataChangeAudit;
use App\Support\Audit\DataChangeAuditContext;
use App\Support\Audit\DataChangeAuditRegistry;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registra en `data_change_audits` cada alta, modificación, eliminación y restauración de las
 * entidades de {@see DataChangeAuditRegistry}, con el valor guardado en la base antes y
 * después de cada campo (sin casts: lo mismo que se ve en la tabla).
 *
 * Nunca interrumpe el guardado: si la auditoría falla, queda en el log y la operación sigue.
 * Los cambios hechos con el query builder (sin modelos) no pasan por aquí.
 */
class DataChangeAuditObserver
{
    private static ?bool $tableExists = null;

    /** @var array<string, ?string> */
    private static array $parentLabels = [];

    public function created(Model $model): void
    {
        $changes = [];

        foreach ($model->getAttributes() as $field => $value) {
            if ($value !== null && $value !== '') {
                $changes[$field] = ['old' => null, 'new' => $value];
            }
        }

        $this->record($model, DataChangeAudit::EVENT_CREATED, $changes);
    }

    public function updated(Model $model): void
    {
        $changes = [];

        foreach ($model->getChanges() as $field => $value) {
            $changes[$field] = ['old' => $model->getRawOriginal($field), 'new' => $value];
        }

        $this->record($model, DataChangeAuditContext::revertingInfo() !== null ? DataChangeAudit::EVENT_REVERTED : DataChangeAudit::EVENT_UPDATED, $changes);
    }

    public function deleted(Model $model): void
    {
        $changes = [];

        /** Foto completa del registro tal como estaba: permite investigar y restaurar. */
        foreach ($model->getAttributes() as $field => $value) {
            if ($value !== null && $value !== '') {
                $changes[$field] = ['old' => $field === 'deleted_at' ? null : $value, 'new' => $field === 'deleted_at' ? $value : null];
            }
        }

        $this->record($model, DataChangeAudit::EVENT_DELETED, $changes);
    }

    public function restored(Model $model): void
    {
        $this->record($model, DataChangeAuditContext::revertingInfo() !== null ? DataChangeAudit::EVENT_REVERTED : DataChangeAudit::EVENT_RESTORED, [
            'deleted_at' => ['old' => $model->getRawOriginal('deleted_at') ?? 'eliminado', 'new' => null],
        ]);
    }

    /**
     * Descarta las caches internas (tests).
     */
    public static function flush(): void
    {
        self::$tableExists = null;
        self::$parentLabels = [];
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    private function record(Model $model, string $event, array $changes): void
    {
        try {
            $definition = DataChangeAuditRegistry::for($model);

            if ($definition === null || $model->getKey() === null) {
                return;
            }

            self::write(
                $definition['key'],
                (int) $model->getKey(),
                self::label($model, $definition['code']),
                self::parentLabel($model, $definition['parent']),
                $event,
                $changes,
                $model->getConnectionName(),
            );
        } catch (Throwable $exception) {
            Log::warning('DataChangeAuditObserver: no se registró el cambio', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Escribe un movimiento (también lo usan los cambios masivos de {@see \App\Support\Audit\DataChangeAuditor}).
     * Devuelve false si no había nada que registrar.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public static function write(string $entityKey, int $entityId, ?string $label, ?string $parentLabel, string $event, array $changes, ?string $connection = null): bool
    {
        if (! self::tableExistsOn($connection)) {
            return false;
        }

        $clean = [];
        $critical = false;

        foreach ($changes as $field => $change) {
            if (in_array($field, DataChangeAuditRegistry::IGNORED_FIELDS, true)) {
                continue;
            }

            $old = self::normalize((string) $field, $change['old']);
            $new = self::normalize((string) $field, $change['new']);

            if ($old === $new && in_array($event, [DataChangeAudit::EVENT_UPDATED, DataChangeAudit::EVENT_REVERTED], true)) {
                continue;
            }

            $clean[$field] = ['old' => $old, 'new' => $new];
            $critical = $critical || DataChangeAuditRegistry::isCritical($entityKey, (string) $field);
        }

        if ($clean === []) {
            return false;
        }

        $user = Auth::user();
        $context = DataChangeAuditContext::describe();
        $reverting = DataChangeAuditContext::revertingInfo();

        DB::connection($connection)->table('data_change_audits')->insert([
            'batch_id' => DataChangeAuditContext::batchId(),
            'entity' => $entityKey,
            'entity_id' => $entityId,
            'entity_label' => $label,
            'parent_label' => $parentLabel,
            'event' => $event,
            'changes' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            'affects_calculations' => $critical,
            'fields' => Str::limit(implode(',', array_keys($clean)), 990, ''),
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user !== null ? Str::limit((string) ($user->name ?? ''), 250, '') : null,
            'source' => $context['source'],
            'panel' => $context['panel'],
            'origin' => $context['origin'],
            'ip' => $context['ip'],
            'user_agent' => $context['user_agent'],
            'reverts_audit_id' => $reverting['audit_id'] ?? null,
            'revert_reason' => $reverting['reason'] ?? null,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ]);

        return true;
    }

    /**
     * Valor tal como se guarda en la base, en texto, enmascarado o recortado si hace falta.
     */
    public static function normalize(string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (in_array($field, DataChangeAuditRegistry::MASKED_FIELDS, true)) {
            return '••••';
        }

        $value = match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? '1' : '0',
            is_array($value), is_object($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };

        if (mb_strlen($value) > DataChangeAuditRegistry::MAX_VALUE_LENGTH) {
            return mb_substr($value, 0, DataChangeAuditRegistry::MAX_VALUE_LENGTH).'… [recortado · sha1 '.sha1($value).']';
        }

        return $value;
    }

    /**
     * @param  list<string>  $fields
     */
    public static function label(Model $model, array $fields): ?string
    {
        $parts = array_filter(array_map(fn (string $field): string => trim((string) $model->getAttribute($field)), $fields), fn (string $part): bool => $part !== '');

        return $parts === [] ? null : Str::limit(implode(' · ', $parts), 250, '');
    }

    /**
     * Código del registro padre (p. ej. la afiliación de un afiliado), una consulta por padre
     * y por petición.
     *
     * @param  array{relation: string, code: string}|null  $parent
     */
    private static function parentLabel(Model $model, ?array $parent): ?string
    {
        if ($parent === null || ! method_exists($model, $parent['relation'])) {
            return null;
        }

        $relation = $model->{$parent['relation']}();
        $foreignKey = method_exists($relation, 'getForeignKeyName') ? $relation->getForeignKeyName() : null;
        $foreign = $foreignKey !== null ? $model->getAttribute($foreignKey) : null;

        if ($foreign === null || $foreign === '') {
            return null;
        }

        $cacheKey = $model::class.':'.$parent['relation'].':'.$foreign;

        if (! array_key_exists($cacheKey, self::$parentLabels)) {
            $related = $relation->getRelated();
            $query = $related->newQueryWithoutScopes()->where($relation->getOwnerKeyName(), $foreign);
            $value = $query->value($parent['code']);
            self::$parentLabels[$cacheKey] = $value !== null ? Str::limit((string) $value, 250, '') : null;
        }

        return self::$parentLabels[$cacheKey];
    }

    private static function tableExistsOn(?string $connection): bool
    {
        return self::$tableExists ??= Schema::connection($connection)->hasTable('data_change_audits');
    }
}

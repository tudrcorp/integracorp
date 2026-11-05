<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\Commission;
use App\Models\DataChangeAudit;
use App\Models\Sale;
use App\Models\User;
use App\Observers\DataChangeAuditObserver;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Revierte un cambio auditado: devuelve los campos a su valor anterior (o restaura el registro
 * eliminado), en una transacción con bloqueo y solo si nadie volvió a cambiar esos campos
 * después. La reversión queda registrada como un cambio más («Revertido»), con la razón; si
 * por cualquier motivo no queda registrada, no se aplica.
 *
 * No recalcula documentos derivados (ventas, comisiones, cuotas): la vista previa los lista
 * para que el analista los revise.
 */
final class DataChangeReverter
{
    public const MIN_REASON_LENGTH = 10;

    /**
     * Qué haría la reversión, sin escribir nada.
     *
     * @return array{revertible: bool, blocker: ?string, mode: ?string, fields: list<array{field: string, label: string, current: ?string, restore: ?string, conflict: bool, critical: bool}>, conflicts: list<string>, dependents: list<string>}
     */
    public static function preview(DataChangeAudit $audit): array
    {
        $result = ['revertible' => false, 'blocker' => null, 'mode' => null, 'fields' => [], 'conflicts' => [], 'dependents' => []];
        $class = DataChangeAuditRegistry::classFor((string) $audit->entity);

        if ($audit->reverted_by_audit_id !== null) {
            return [...$result, 'blocker' => 'Este cambio ya fue revertido (movimiento #'.$audit->reverted_by_audit_id.').'];
        }

        if ($class === null) {
            return [...$result, 'blocker' => 'La entidad de este cambio ya no se audita.'];
        }

        if ($audit->event === DataChangeAudit::EVENT_CREATED) {
            return [...$result, 'blocker' => 'Revertir una creación sería borrar el registro. Si debe eliminarse, hágalo desde su módulo: quedará auditado.'];
        }

        if ($audit->event === DataChangeAudit::EVENT_RESTORED) {
            return [...$result, 'blocker' => 'Para volver a eliminar este registro use la eliminación de su módulo: quedará auditada.'];
        }

        $model = self::model($class, (int) $audit->entity_id);

        if ($model === null) {
            return [...$result, 'blocker' => 'El registro ya no existe (se eliminó de forma permanente). El detalle conserva la foto de sus datos.'];
        }

        $result['dependents'] = self::dependents($model);

        if ($audit->event === DataChangeAudit::EVENT_DELETED) {
            if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                return [...$result, 'blocker' => 'Esta entidad se elimina de forma permanente: no se puede restaurar desde aquí. El detalle conserva la foto de sus datos.'];
            }

            if (! $model->trashed()) {
                return [...$result, 'blocker' => 'El registro ya no está eliminado: alguien lo restauró después.'];
            }

            return [...$result, 'revertible' => true, 'mode' => 'restore'];
        }

        $later = self::laterChanges($audit);

        foreach ((array) $audit->changes as $field => $change) {
            $current = DataChangeAuditObserver::normalize($field, $model->getAttributes()[$field] ?? null);
            $conflict = ! self::same($current, $change['new'] ?? null) || isset($later[$field]);
            $unrestorable = self::unrestorable($change['old'] ?? null) || self::unrestorable($change['new'] ?? null);

            $result['fields'][] = [
                'field' => $field,
                'label' => DataChangeAuditRegistry::fieldLabel($field),
                'current' => $current,
                'restore' => $change['old'] ?? null,
                'conflict' => $conflict,
                'critical' => DataChangeAuditRegistry::isCritical((string) $audit->entity, $field),
            ];

            if ($conflict) {
                $result['conflicts'][] = DataChangeAuditRegistry::fieldLabel($field).(isset($later[$field]) ? ' cambió después: '.$later[$field] : ' ya no tiene el valor que dejó este cambio.');
            }

            if ($unrestorable) {
                $result['conflicts'][] = DataChangeAuditRegistry::fieldLabel($field).': el valor está enmascarado o recortado y no se puede restaurar con exactitud.';
            }
        }

        if ($result['conflicts'] !== []) {
            return [...$result, 'blocker' => 'No se puede revertir sin pisar cambios posteriores. Revise primero los movimientos siguientes de este registro.'];
        }

        return [...$result, 'revertible' => true, 'mode' => 'fields'];
    }

    /**
     * Aplica la reversión y devuelve el movimiento que la registra.
     */
    public static function revert(DataChangeAudit $audit, string $reason, User $user): DataChangeAudit
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Indique la razón de la reversión (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($audit, $reason, $user): DataChangeAudit {
            /** Bloquea el movimiento: dos analistas no pueden revertir lo mismo a la vez. */
            $locked = DataChangeAudit::query()->lockForUpdate()->findOrFail($audit->getKey());
            $class = DataChangeAuditRegistry::classFor((string) $locked->entity) ?? throw new InvalidArgumentException('La entidad de este cambio ya no se audita.');
            $model = self::model($class, (int) $locked->entity_id, lock: true) ?? throw new InvalidArgumentException('El registro ya no existe.');

            /** Se vuelve a validar con el registro bloqueado: nada pudo cambiar entre la vista previa y aquí. */
            $preview = self::preview($locked);

            if (! $preview['revertible']) {
                throw new InvalidArgumentException($preview['blocker'] ?? 'Este cambio no se puede revertir.');
            }

            DataChangeAuditContext::reverting((int) $locked->getKey(), $reason, function () use ($model, $locked, $preview): void {
                if ($preview['mode'] === 'restore') {
                    $model->restore();

                    return;
                }

                $raw = [];

                foreach ((array) $locked->changes as $field => $change) {
                    $raw[$field] = $change['old'] ?? null;
                }

                $model->setRawAttributes(array_merge($model->getAttributes(), $raw));

                if (! $model->save()) {
                    throw new RuntimeException('No se pudo guardar la reversión.');
                }
            });

            $revert = DataChangeAudit::query()
                ->where('reverts_audit_id', $locked->getKey())
                ->where('entity', $locked->entity)
                ->where('entity_id', $locked->entity_id)
                ->latest('id')
                ->first();

            /** Una reversión sin registro no se acepta: se deshace todo. */
            if (! $revert instanceof DataChangeAudit) {
                throw new RuntimeException('La reversión no quedó registrada en la auditoría y se canceló.');
            }

            $locked->forceFill(['reverted_by_audit_id' => $revert->getKey()])->save();

            SecurityAudit::log('AUDIT_DATA_CHANGE_REVERTED', 'administration.data-change-audits.revert', [
                'audit_id' => $locked->getKey(),
                'revert_audit_id' => $revert->getKey(),
                'entity' => $locked->entity,
                'entity_id' => $locked->entity_id,
                'reason' => $reason,
            ], $user);

            return $revert;
        });
    }

    /**
     * Campos que otro movimiento cambió después de este: campo => «quién · cuándo».
     *
     * @return array<string, string>
     */
    public static function laterChanges(DataChangeAudit $audit): array
    {
        $later = [];

        DataChangeAudit::query()
            ->where('entity', $audit->entity)
            ->where('entity_id', $audit->entity_id)
            ->where('id', '>', $audit->getKey())
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'changes', 'user_name', 'created_at', 'event'])
            ->each(function (DataChangeAudit $next) use (&$later, $audit): void {
                foreach (array_keys((array) $next->changes) as $field) {
                    if (array_key_exists($field, (array) $audit->changes) && ! isset($later[$field])) {
                        $later[$field] = ($next->user_name ?: 'Sistema').' · '.$next->created_at?->format('d/m/Y h:i A').' (#'.$next->getKey().')';
                    }
                }
            });

        return $later;
    }

    /**
     * Documentos que dependen del registro y que la reversión NO recalcula.
     *
     * @return list<string>
     */
    public static function dependents(Model $model): array
    {
        $code = match (true) {
            $model instanceof Affiliation, $model instanceof AffiliationCorporate => (string) $model->code,
            default => null,
        };

        $lines = [];

        if ($code !== null && $code !== '') {
            $sales = Sale::query()->where('affiliation_code', $code)->count();
            $commissions = Commission::query()->where('affiliation_code', $code)->count();
            $collections = Collection::query()->where('affiliation_code', $code)->count();

            foreach ([[$sales, 'venta', 'ventas'], [$commissions, 'comisión', 'comisiones'], [$collections, 'cuota de cobranza', 'cuotas de cobranza']] as [$count, $one, $many]) {
                if ($count > 0) {
                    $lines[] = $count.' '.($count === 1 ? $one : $many).' de '.$code.' ya generada'.($count === 1 ? '' : 's').' con los datos de su momento.';
                }
            }
        }

        if ($model instanceof Agent) {
            $count = Affiliation::query()->where('agent_id', $model->getKey())->count();
            $lines[] = $count.' afiliaciones individuales tienen asignado este agente.';
        }

        if ($model instanceof Agency) {
            $count = Affiliation::query()->where('code_agency', $model->code)->count();
            $lines[] = $count.' afiliaciones individuales están en esta agencia.';
        }

        return $lines;
    }

    private static function model(string $class, int $id, bool $lock = false): ?Model
    {
        /** @var Model $instance */
        $instance = new $class;
        $query = in_array(SoftDeletes::class, class_uses_recursive($instance), true)
            ? $class::query()->withTrashed()
            : $class::query();

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->find($id);
    }

    /**
     * Mismo valor: numéricos por valor (163.5 = 163.50), el resto por texto.
     */
    private static function same(?string $a, ?string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if ($a !== null && $b !== null && is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return false;
    }

    private static function unrestorable(?string $value): bool
    {
        return $value !== null && ($value === '••••' || str_contains($value, '… [recortado · sha1 '));
    }
}

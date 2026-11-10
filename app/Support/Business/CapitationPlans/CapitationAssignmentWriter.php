<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Models\AffiliateCapitationAssignment;
use App\Models\AffiliateCapitationAssignmentLog;
use App\Models\CapitationPlan;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Asignación directa de un plan de capitado a afiliados: asignar, quitar y deshacer.
 *
 * Reglas (también impuestas aquí, no solo en la pantalla), decididas el 07/11/2026:
 * - el plan debe estar ACTIVO, vigente hoy y ser de un proveedor jurídico Outsourcing;
 * - el proveedor del plan debe estar en los proveedores de servicio de la afiliación de cada
 *   afiliado (en Nuevos Negocios, de su empresa); no se exige que el plan cubra su plan TDEC;
 * - razón obligatoria; todo o nada (si un seleccionado no cumple, no se toca ninguno);
 * - historial y auditoría; el último cambio de cada afiliado se deshace al estado exacto anterior.
 */
final class CapitationAssignmentWriter
{
    public const MAX_RECORDS = CapitationOverrideWriter::MAX_RECORDS;

    public const MIN_REASON = CapitationOverrideWriter::MIN_REASON;

    public const MAX_REASON = CapitationOverrideWriter::MAX_REASON;

    /**
     * Planes que se pueden asignar hoy: id => «Plan · Proveedor · US$ costo · frecuencia».
     *
     * @return array<int, string>
     */
    public static function planOptions(): array
    {
        return self::assignablePlans()
            ->mapWithKeys(fn (CapitationPlan $plan): array => [
                (int) $plan->getKey() => $plan->name.' · '.$plan->supplier?->name.' · '.CapitationCostResult::money((float) $plan->cost_per_affiliate_usd).' · '.mb_strtolower($plan->payment_frequency->label()),
            ])
            ->all();
    }

    /**
     * Planes asignables a este afiliado: además, su proveedor debe estar en la afiliación. Si la
     * afiliación no lleva ningún proveedor Outsourcing con plan, la lista sale vacía.
     *
     * @return array<int, string>
     */
    public static function planOptionsFor(Model $record): array
    {
        $aliases = AffiliateCapitationCost::aliases(AffiliateCapitationCost::serviceProvidersFor($record));
        $options = self::planOptions();

        return self::assignablePlans()
            ->filter(fn (CapitationPlan $plan): bool => in_array(CapitationCostIndex::normalize((string) $plan->supplier?->integracorp_alias), $aliases, true))
            ->mapWithKeys(fn (CapitationPlan $plan): array => [(int) $plan->getKey() => $options[(int) $plan->getKey()]])
            ->all();
    }

    /**
     * @param  iterable<Model>  $records
     * @return int afiliados con el plan asignado
     */
    public static function assign(iterable $records, mixed $planId, string $reason, ?User $user): int
    {
        $records = self::records($records);
        $reason = self::reason($reason);
        $plan = self::assignablePlans()->firstWhere('id', (int) $planId);

        if (! $plan instanceof CapitationPlan) {
            throw ValidationException::withMessages(['capitation_plan_id' => 'Elija un plan de capitado activo y vigente de un proveedor Outsourcing.']);
        }

        $alias = CapitationCostIndex::normalize((string) $plan->supplier?->integracorp_alias);
        $blocked = $records->reject(fn (Model $record): bool => in_array($alias, AffiliateCapitationCost::aliases(AffiliateCapitationCost::serviceProvidersFor($record)), true));

        if ($blocked->isNotEmpty()) {
            $names = $blocked->take(5)->map(fn (Model $record): string => CapitationSubject::label($record))->implode('; ');
            $more = $blocked->count() > 5 ? ' y '.($blocked->count() - 5).' más' : '';

            throw ValidationException::withMessages(['capitation_plan_id' => 'No se asignó a ninguno: el proveedor '.$plan->supplier?->name.' no está en los proveedores de servicio de la afiliación de '.$names.$more.'.']);
        }

        $batch = (string) Str::uuid();
        $userName = self::userName($user);

        DB::transaction(function () use ($records, $plan, $reason, $user, $userName, $batch): void {
            $locked = CapitationPlan::query()->whereKey($plan->getKey())->lockForUpdate()->first();

            if (! $locked instanceof CapitationPlan || $locked->status !== CapitationPlan::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['capitation_plan_id' => 'El plan de capitado dejó de estar activo. Recargue la página.']);
            }

            $current = self::lockCurrent($records);

            foreach ($records as $record) {
                $previous = $current->get(CapitationSubject::key($record));

                AffiliateCapitationAssignment::query()->updateOrCreate(
                    ['subject_type' => CapitationSubject::type($record), 'subject_id' => (int) $record->getKey()],
                    [
                        'capitation_plan_id' => (int) $plan->getKey(),
                        'reason' => $reason,
                        'created_by_user_id' => $user?->getKey(),
                        'created_by_name' => $userName,
                    ],
                );

                self::log($record, AffiliateCapitationAssignmentLog::ACTION_ASSIGN, $previous, (int) $plan->getKey(), $reason, $batch, $user, $userName);
            }
        });

        self::audit('AUDIT_CAPITATION_PLAN_ASSIGNED', $records, ['capitation_plan_id' => (int) $plan->getKey(), 'reason' => $reason, 'batch_uuid' => $batch], $user);
        AffiliateCapitationCost::flush();

        return $records->count();
    }

    /**
     * Quita el plan asignado: el afiliado vuelve al cálculo automático. Los que no tienen
     * asignación se ignoran.
     *
     * @param  iterable<Model>  $records
     */
    public static function remove(iterable $records, string $reason, ?User $user): int
    {
        $records = self::records($records);
        $reason = self::reason($reason);
        $batch = (string) Str::uuid();
        $userName = self::userName($user);

        $removed = DB::transaction(function () use ($records, $reason, $user, $userName, $batch): int {
            $current = self::lockCurrent($records);

            if ($current->isEmpty()) {
                throw ValidationException::withMessages(['reason' => $records->count() === 1
                    ? 'Este afiliado no tiene un plan de capitado asignado.'
                    : 'Ninguno de los seleccionados tiene un plan de capitado asignado.']);
            }

            foreach ($records as $record) {
                $previous = $current->get(CapitationSubject::key($record));

                if ($previous === null) {
                    continue;
                }

                $previous->delete();
                self::log($record, AffiliateCapitationAssignmentLog::ACTION_REMOVE, $previous, null, $reason, $batch, $user, $userName);
            }

            return $current->count();
        });

        self::audit('AUDIT_CAPITATION_PLAN_UNASSIGNED', $records, ['reason' => $reason, 'removed' => $removed, 'batch_uuid' => $batch], $user);
        AffiliateCapitationCost::flush();

        return $removed;
    }

    /**
     * Deshace el último cambio de cada afiliado. Todo o nada: se bloquea si alguno no tiene
     * cambios por deshacer, si su estado actual ya no es el que dejó ese cambio, o si deshacer
     * vuelve a un plan que ya no se puede asignar.
     *
     * @param  iterable<Model>  $records
     */
    public static function revert(iterable $records, string $reason, ?User $user): int
    {
        $records = self::records($records);
        $reason = self::reason($reason);
        $batch = (string) Str::uuid();
        $userName = self::userName($user);
        $assignable = self::assignablePlans()->keyBy('id');

        DB::transaction(function () use ($records, $reason, $user, $userName, $batch, $assignable): void {
            $current = self::lockCurrent($records);
            $blocked = [];
            $plans = [];

            foreach ($records as $record) {
                $key = CapitationSubject::key($record);
                $target = self::undoableQuery($record)->lockForUpdate()->first();
                $now = $current->get($key);

                $problem = match (true) {
                    ! $target instanceof AffiliateCapitationAssignmentLog => 'no tiene cambios por deshacer',
                    ! self::stateMatches($target, $now) => 'su plan asignado cambió después',
                    $target->previous_capitation_plan_id !== null && ! $assignable->has($target->previous_capitation_plan_id) => 'el plan anterior ya no está activo o vigente',
                    $target->previous_capitation_plan_id !== null && ! in_array(
                        CapitationCostIndex::normalize((string) $assignable->get($target->previous_capitation_plan_id)?->supplier?->integracorp_alias),
                        AffiliateCapitationCost::aliases(AffiliateCapitationCost::serviceProvidersFor($record)),
                        true,
                    ) => 'el proveedor del plan anterior ya no está en la afiliación',
                    default => null,
                };

                if ($problem !== null) {
                    $blocked[] = CapitationSubject::label($record).' ('.$problem.')';

                    continue;
                }

                $plans[$key] = [$record, $target, $now];
            }

            if ($blocked !== []) {
                $more = count($blocked) > 5 ? ' y '.(count($blocked) - 5).' más' : '';

                throw ValidationException::withMessages(['reason' => 'No se deshizo ningún cambio. Revise: '.implode('; ', array_slice($blocked, 0, 5)).$more.'.']);
            }

            foreach ($plans as [$record, $target, $now]) {
                $restored = $target->previous_capitation_plan_id;

                if ($restored === null) {
                    $now?->delete();
                } else {
                    AffiliateCapitationAssignment::query()->updateOrCreate(
                        ['subject_type' => CapitationSubject::type($record), 'subject_id' => (int) $record->getKey()],
                        [
                            'capitation_plan_id' => $restored,
                            'reason' => (string) ($target->previous_reason ?: 'Restaurado: '.$target->reason),
                            'created_by_user_id' => $user?->getKey(),
                            'created_by_name' => $userName,
                        ],
                    );
                }

                self::log($record, AffiliateCapitationAssignmentLog::ACTION_REVERT, $now, $restored, $reason, $batch, $user, $userName, (int) $target->getKey());
                $target->forceFill(['reverted_at' => now(), 'reverted_by_name' => $userName])->save();
            }
        });

        self::audit('AUDIT_CAPITATION_PLAN_ASSIGNMENT_REVERTED', $records, ['reason' => $reason, 'batch_uuid' => $batch], $user);
        AffiliateCapitationCost::flush();

        return $records->count();
    }

    public static function latestUndoable(Model $record): ?AffiliateCapitationAssignmentLog
    {
        return self::undoableQuery($record)->first();
    }

    /**
     * @return SupportCollection<int, AffiliateCapitationAssignmentLog>
     */
    public static function history(Model $record, int $limit = 5): SupportCollection
    {
        return AffiliateCapitationAssignmentLog::query()
            ->where('subject_type', CapitationSubject::type($record))
            ->where('subject_id', (int) $record->getKey())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CapitationPlan>
     */
    private static function assignablePlans(): Collection
    {
        $today = CarbonImmutable::now();

        return CapitationPlan::query()
            ->where('status', CapitationPlan::STATUS_ACTIVE)
            ->where('provider_type', CapitationPlan::PROVIDER_SUPPLIER)
            ->with('supplier:id,name,integracorp_alias,is_outsourcing_medical_department,deleted_at')
            ->orderBy('name')
            ->get()
            ->filter(fn (CapitationPlan $plan): bool => $plan->supplier instanceof Supplier
                && ! $plan->supplier->trashed()
                && (bool) $plan->supplier->is_outsourcing_medical_department
                && CapitationCostIndex::normalize((string) $plan->supplier->integracorp_alias) !== ''
                && $plan->isValidOn($today))
            ->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AffiliateCapitationAssignmentLog>
     */
    private static function undoableQuery(Model $record): \Illuminate\Database\Eloquent\Builder
    {
        return AffiliateCapitationAssignmentLog::query()
            ->where('subject_type', CapitationSubject::type($record))
            ->where('subject_id', (int) $record->getKey())
            ->whereIn('action', [AffiliateCapitationAssignmentLog::ACTION_ASSIGN, AffiliateCapitationAssignmentLog::ACTION_REMOVE])
            ->whereNull('reverted_at')
            ->orderByDesc('id');
    }

    private static function stateMatches(AffiliateCapitationAssignmentLog $target, ?AffiliateCapitationAssignment $now): bool
    {
        if ($target->action === AffiliateCapitationAssignmentLog::ACTION_REMOVE) {
            return $now === null;
        }

        return $now !== null && (int) $now->capitation_plan_id === (int) $target->new_capitation_plan_id;
    }

    /**
     * @param  iterable<Model>  $records
     * @return Collection<int, Model>
     */
    private static function records(iterable $records): Collection
    {
        $records = (new Collection(is_array($records) ? $records : iterator_to_array($records, false)))
            ->filter(fn (mixed $record): bool => $record instanceof Model && $record->exists)
            ->unique(fn (Model $record): string => CapitationSubject::key($record))
            ->values();

        if ($records->isEmpty()) {
            throw ValidationException::withMessages(['capitation_plan_id' => 'Seleccione al menos un afiliado.']);
        }

        if ($records->count() > self::MAX_RECORDS) {
            throw ValidationException::withMessages(['capitation_plan_id' => 'Se pueden asignar hasta '.self::MAX_RECORDS.' afiliados a la vez.']);
        }

        $records->groupBy(fn (Model $record): string => CapitationSubject::type($record))
            ->each(fn (Collection $group, string $type) => $group->loadMissing(CapitationSubject::relationsFor($type)));

        return $records;
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return SupportCollection<string, AffiliateCapitationAssignment>
     */
    private static function lockCurrent(Collection $records): SupportCollection
    {
        $current = new SupportCollection;

        foreach ($records->groupBy(fn (Model $record): string => CapitationSubject::type($record)) as $type => $group) {
            AffiliateCapitationAssignment::query()
                ->where('subject_type', $type)
                ->whereIn('subject_id', $group->modelKeys())
                ->lockForUpdate()
                ->get()
                ->each(fn (AffiliateCapitationAssignment $row) => $current->put($row->subject_type.':'.$row->subject_id, $row));
        }

        return $current;
    }

    private static function reason(string $reason): string
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::MIN_REASON || mb_strlen($reason) > self::MAX_REASON) {
            throw ValidationException::withMessages([
                'reason' => 'Explique la razón (entre '.self::MIN_REASON.' y '.self::MAX_REASON.' caracteres).',
            ]);
        }

        return $reason;
    }

    private static function log(
        Model $record,
        string $action,
        ?AffiliateCapitationAssignment $previous,
        ?int $newPlanId,
        string $reason,
        string $batch,
        ?User $user,
        string $userName,
        ?int $revertsLogId = null,
    ): void {
        AffiliateCapitationAssignmentLog::query()->create([
            'subject_type' => CapitationSubject::type($record),
            'subject_id' => (int) $record->getKey(),
            'action' => $action,
            'previous_capitation_plan_id' => $previous?->capitation_plan_id,
            'new_capitation_plan_id' => $newPlanId,
            'previous_reason' => $previous?->reason,
            'reason' => $reason,
            'batch_uuid' => $batch,
            'reverts_log_id' => $revertsLogId,
            'created_by_user_id' => $user?->getKey(),
            'created_by_name' => $userName,
        ]);
    }

    /**
     * @param  Collection<int, Model>  $records
     * @param  array<string, mixed>  $details
     */
    private static function audit(string $action, Collection $records, array $details, ?User $user): void
    {
        SecurityAudit::log($action, 'capitation.assignment', [
            ...$details,
            'subjects' => $records->map(fn (Model $record): string => CapitationSubject::key($record))->all(),
        ], $user);
    }

    private static function userName(?User $user): string
    {
        return filled($user?->name) ? (string) $user->name : 'SISTEMA';
    }
}

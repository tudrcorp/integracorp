<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Models\AffiliateCapitationOverride;
use App\Models\AffiliateCapitationOverrideLog;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ajuste negociado del capitado por afiliado: fijarlo y retirarlo.
 *
 * Reglas (también impuestas aquí, no solo en la pantalla):
 * - solo se ajusta un afiliado al que hoy le aplica un plan de capitado (activo, con
 *   proveedor Outsourcing y plan vigente que cubre su plan TDEC);
 * - razón obligatoria siempre;
 * - todo o nada: si un seleccionado no cumple, no se toca ninguno;
 * - cada cambio deja historial (con el monto del plan de ese momento) y auditoría;
 * - el último cambio de cada afiliado se puede deshacer y vuelve al estado exacto anterior.
 */
final class CapitationOverrideWriter
{
    public const MAX_RECORDS = 500;

    public const MIN_REASON = 10;

    public const MAX_REASON = 1000;

    public const MAX_AMOUNT = 99999999.99;

    /**
     * @param  iterable<Model>  $records
     * @return int afiliados ajustados
     */
    public static function set(iterable $records, mixed $amount, string $reason, ?User $user): int
    {
        $records = self::records($records);
        $reason = self::reason($reason);

        if (! is_numeric($amount) || (float) $amount < 0 || (float) $amount > self::MAX_AMOUNT) {
            throw ValidationException::withMessages(['amount' => 'Escriba un monto válido en US$ (0,00 o mayor).']);
        }

        $amount = round((float) $amount, 2);
        $base = self::baseResults($records);

        $blocked = $records->filter(fn (Model $record): bool => ! $base[CapitationSubject::key($record)]->applies());

        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages(['amount' => self::blockedMessage($blocked, $base)]);
        }

        $batch = (string) Str::uuid();
        $userName = self::userName($user);

        DB::transaction(function () use ($records, $amount, $reason, $user, $userName, $batch, $base): void {
            $current = self::lockCurrent($records);

            foreach ($records as $record) {
                $key = CapitationSubject::key($record);
                $result = $base[$key];
                $previous = $current->get($key);

                AffiliateCapitationOverride::query()->updateOrCreate(
                    ['subject_type' => CapitationSubject::type($record), 'subject_id' => (int) $record->getKey()],
                    [
                        'amount_usd' => $amount,
                        'reason' => $reason,
                        'capitation_plan_id' => $result->lines[0]['capitation_plan_id'] ?? null,
                        'plan_amount_usd' => $result->planAmount,
                        'created_by_user_id' => $user?->getKey(),
                        'created_by_name' => $userName,
                    ],
                );

                self::log($record, AffiliateCapitationOverrideLog::ACTION_SET, $previous, $amount, $result, $reason, $batch, $user, $userName);
            }
        });

        self::audit('AUDIT_CAPITATION_OVERRIDE_SET', $records, ['amount' => $amount, 'reason' => $reason, 'batch_uuid' => $batch], $user);
        AffiliateCapitationCost::flush();

        return $records->count();
    }

    /**
     * Vuelve al monto del plan. Los seleccionados sin ajuste se ignoran.
     *
     * @param  iterable<Model>  $records
     * @return int ajustes retirados
     */
    public static function remove(iterable $records, string $reason, ?User $user): int
    {
        $records = self::records($records);
        $reason = self::reason($reason);
        $batch = (string) Str::uuid();
        $userName = self::userName($user);
        $base = self::baseResults($records);

        $removed = DB::transaction(function () use ($records, $reason, $user, $userName, $batch, $base): int {
            $current = self::lockCurrent($records);

            if ($current->isEmpty()) {
                throw ValidationException::withMessages(['reason' => $records->count() === 1
                    ? 'Este afiliado no tiene un monto negociado: ya usa el monto del plan.'
                    : 'Ninguno de los seleccionados tiene un monto negociado.']);
            }

            foreach ($records as $record) {
                $key = CapitationSubject::key($record);
                $previous = $current->get($key);

                if ($previous === null) {
                    continue;
                }

                $previous->delete();
                self::log($record, AffiliateCapitationOverrideLog::ACTION_REMOVE, $previous, null, $base[$key], $reason, $batch, $user, $userName);
            }

            return $current->count();
        });

        self::audit('AUDIT_CAPITATION_OVERRIDE_REMOVED', $records, ['reason' => $reason, 'removed' => $removed, 'batch_uuid' => $batch], $user);
        AffiliateCapitationCost::flush();

        return $removed;
    }

    /**
     * Deshace el último cambio (ajuste o retiro) de cada afiliado y lo deja exactamente como
     * estaba antes: con su monto, razón y monto de plan anteriores, o sin ajuste.
     *
     * Todo o nada. Se bloquea si un afiliado no tiene cambios por deshacer, si su estado actual
     * ya no es el que dejó ese cambio (alguien lo tocó después) o si deshacer recrea un monto
     * negociado y el plan de capitado ya no le aplica.
     *
     * @param  iterable<Model>  $records
     * @return int afiliados restaurados
     */
    public static function revert(iterable $records, string $reason, ?User $user): int
    {
        $records = self::records($records);
        $reason = self::reason($reason);
        $batch = (string) Str::uuid();
        $userName = self::userName($user);
        $base = self::baseResults($records);

        DB::transaction(function () use ($records, $reason, $user, $userName, $batch, $base): void {
            $current = self::lockCurrent($records);
            $blocked = [];
            $plans = [];

            foreach ($records as $record) {
                $key = CapitationSubject::key($record);
                $target = self::undoableQuery($record)->lockForUpdate()->first();
                $now = $current->get($key);

                $problem = match (true) {
                    ! $target instanceof AffiliateCapitationOverrideLog => 'no tiene cambios por deshacer',
                    ! self::stateMatches($target, $now) => 'su monto cambió después; ajústelo de nuevo en vez de deshacer',
                    $target->previous_amount_usd !== null && ! $base[$key]->applies() => 'ya no le aplica un plan de capitado',
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

            foreach ($plans as $key => [$record, $target, $now]) {
                $restored = $target->previous_amount_usd !== null ? round((float) $target->previous_amount_usd, 2) : null;

                if ($restored === null) {
                    $now?->delete();
                } else {
                    AffiliateCapitationOverride::query()->updateOrCreate(
                        ['subject_type' => CapitationSubject::type($record), 'subject_id' => (int) $record->getKey()],
                        [
                            'amount_usd' => $restored,
                            'reason' => (string) ($target->previous_reason ?: 'Restaurado: '.$target->reason),
                            'capitation_plan_id' => $base[$key]->lines[0]['capitation_plan_id'] ?? null,
                            'plan_amount_usd' => $target->previous_plan_amount_usd ?? $base[$key]->planAmount,
                            'created_by_user_id' => $user?->getKey(),
                            'created_by_name' => $userName,
                        ],
                    );
                }

                self::log($record, AffiliateCapitationOverrideLog::ACTION_REVERT, $now, $restored, $base[$key], $reason, $batch, $user, $userName, (int) $target->getKey());

                $target->forceFill(['reverted_at' => now(), 'reverted_by_name' => $userName])->save();
            }
        });

        self::audit('AUDIT_CAPITATION_OVERRIDE_REVERTED', $records, ['reason' => $reason, 'batch_uuid' => $batch], $user);
        AffiliateCapitationCost::flush();

        return $records->count();
    }

    /**
     * El último cambio que se puede deshacer, para mostrarlo antes de confirmar.
     */
    public static function latestUndoable(Model $record): ?AffiliateCapitationOverrideLog
    {
        return self::undoableQuery($record)->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AffiliateCapitationOverrideLog>
     */
    private static function undoableQuery(Model $record): \Illuminate\Database\Eloquent\Builder
    {
        return AffiliateCapitationOverrideLog::query()
            ->where('subject_type', CapitationSubject::type($record))
            ->where('subject_id', (int) $record->getKey())
            ->whereIn('action', [AffiliateCapitationOverrideLog::ACTION_SET, AffiliateCapitationOverrideLog::ACTION_REMOVE])
            ->whereNull('reverted_at')
            ->orderByDesc('id');
    }

    /**
     * El estado actual es exactamente el que dejó el cambio: si no, alguien lo modificó después.
     */
    private static function stateMatches(AffiliateCapitationOverrideLog $target, ?AffiliateCapitationOverride $now): bool
    {
        if ($target->action === AffiliateCapitationOverrideLog::ACTION_REMOVE) {
            return $now === null;
        }

        return $now !== null && abs((float) $now->amount_usd - (float) $target->new_amount_usd) < 0.005;
    }

    /**
     * @return SupportCollection<int, AffiliateCapitationOverrideLog>
     */
    public static function history(Model $record, int $limit = 10): SupportCollection
    {
        return AffiliateCapitationOverrideLog::query()
            ->where('subject_type', CapitationSubject::type($record))
            ->where('subject_id', (int) $record->getKey())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
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
            throw ValidationException::withMessages(['amount' => 'Seleccione al menos un afiliado.']);
        }

        if ($records->count() > self::MAX_RECORDS) {
            throw ValidationException::withMessages(['amount' => 'Se pueden ajustar hasta '.self::MAX_RECORDS.' afiliados a la vez.']);
        }

        /** Precarga en lote lo que el cálculo necesita: sin una consulta por afiliado. */
        $records->groupBy(fn (Model $record): string => CapitationSubject::type($record))
            ->each(fn (Collection $group, string $type) => $group->loadMissing(CapitationSubject::relationsFor($type)));

        return $records;
    }

    /**
     * Monto del plan sin el ajuste: es lo que se compara y lo que queda en el historial.
     *
     * @param  Collection<int, Model>  $records
     * @return array<string, CapitationCostResult>
     */
    private static function baseResults(Collection $records): array
    {
        AffiliateCapitationCost::flush();
        $results = [];

        foreach ($records as $record) {
            $results[CapitationSubject::key($record)] = CapitationSubject::resolve($record, applyOverride: false);
        }

        return $results;
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return SupportCollection<string, AffiliateCapitationOverride>
     */
    private static function lockCurrent(Collection $records): SupportCollection
    {
        $current = new SupportCollection;

        foreach ($records->groupBy(fn (Model $record): string => CapitationSubject::type($record)) as $type => $group) {
            AffiliateCapitationOverride::query()
                ->where('subject_type', $type)
                ->whereIn('subject_id', $group->modelKeys())
                ->lockForUpdate()
                ->get()
                ->each(fn (AffiliateCapitationOverride $row) => $current->put($row->subject_type.':'.$row->subject_id, $row));
        }

        return $current;
    }

    private static function reason(string $reason): string
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::MIN_REASON || mb_strlen($reason) > self::MAX_REASON) {
            throw ValidationException::withMessages([
                'reason' => 'Explique la razón del ajuste (entre '.self::MIN_REASON.' y '.self::MAX_REASON.' caracteres).',
            ]);
        }

        return $reason;
    }

    /**
     * @param  Collection<int, Model>  $blocked
     * @param  array<string, CapitationCostResult>  $base
     */
    private static function blockedMessage(Collection $blocked, array $base): string
    {
        $names = $blocked->take(5)
            ->map(fn (Model $record): string => CapitationSubject::label($record).' ('.mb_strtolower((string) $base[CapitationSubject::key($record)]->reason).')')
            ->implode('; ');

        $more = $blocked->count() > 5 ? ' y '.($blocked->count() - 5).' más' : '';

        return 'No se ajustó ninguno: solo se puede negociar el monto de afiliados activos a los que les aplica un plan de capitado. Revise: '.$names.$more.'.';
    }

    private static function log(
        Model $record,
        string $action,
        ?AffiliateCapitationOverride $previous,
        ?float $new,
        CapitationCostResult $result,
        string $reason,
        string $batch,
        ?User $user,
        string $userName,
        ?int $revertsLogId = null,
    ): AffiliateCapitationOverrideLog {
        return AffiliateCapitationOverrideLog::query()->create([
            'subject_type' => CapitationSubject::type($record),
            'subject_id' => (int) $record->getKey(),
            'action' => $action,
            'previous_amount_usd' => $previous !== null ? (float) $previous->amount_usd : null,
            'new_amount_usd' => $new,
            'previous_reason' => $previous?->reason,
            'previous_plan_amount_usd' => $previous?->plan_amount_usd !== null ? (float) $previous->plan_amount_usd : null,
            'reverts_log_id' => $revertsLogId,
            'plan_amount_usd' => $result->applies() ? $result->planAmount : null,
            'capitation_plan_id' => $result->lines[0]['capitation_plan_id'] ?? null,
            'reason' => $reason,
            'batch_uuid' => $batch,
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
        SecurityAudit::log($action, 'capitation.override', [
            ...$details,
            'subjects' => $records->map(fn (Model $record): string => CapitationSubject::key($record))->all(),
        ], $user);
    }

    private static function userName(?User $user): string
    {
        return filled($user?->name) ? (string) $user->name : 'SISTEMA';
    }
}

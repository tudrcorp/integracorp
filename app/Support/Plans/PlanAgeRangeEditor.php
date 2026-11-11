<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\SecurityAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cambia las edades de un rango de edad de un plan.
 *
 * El rango se referencia por id en tarifas, filas de plan de afiliaciones,
 * cotizaciones y renovaciones: todas esas pantallas lo toman en el acto. Lo que
 * guarda el TEXTO copiado («1 a 99») se actualiza en la misma transacción:
 * `fees.range`, `fee_plans.range` y `plan_generator_rate_rows.age_range_label`.
 *
 * Reglas (decisión de Gustavo, 11/11/2026):
 *  - No se edita un rango que usan otros planes (cambiaría precios ajenos).
 *  - No se cruza con otro rango activo del mismo plan y cobertura.
 *  - Si al achicarlo algún afiliado activo o renovación pendiente queda sin
 *    rango, se bloquea y se muestra la lista.
 *  - Los precios no cambian. Los PDF ya emitidos no se regeneran.
 */
final class PlanAgeRangeEditor
{
    public const REASON_MIN_LENGTH = 15;

    public const MAX_AGE = 120;

    /** Personas que se muestran en el detalle de un bloqueo. */
    private const LIST_LIMIT = 30;

    /** Estatus de afiliado que cuentan como vigentes. */
    private const ACTIVE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    /**
     * Rangos de edad editables del plan: los suyos activos y los que usan sus tarifas activas.
     *
     * @return Collection<int, AgeRange>
     */
    public static function rangesFor(Plan $plan): Collection
    {
        $fromFees = Fee::query()
            ->where('plan_id', $plan->getKey())
            ->where(static fn ($query) => $query->where('status', 'ACTIVO')->orWhereNull('status'))
            ->whereNotNull('age_range_id')
            ->pluck('age_range_id');

        return AgeRange::query()
            ->where(static fn ($query) => $query
                ->whereIn('id', $fromFees->all() ?: [0])
                ->orWhere(static fn ($own) => $own->where('plan_id', $plan->getKey())->where(static fn ($status) => $status->where('status', 'ACTIVO')->orWhereNull('status'))))
            ->orderBy('age_init')
            ->orderBy('id')
            ->get();
    }

    /**
     * Si algo del sistema apunta al rango (no se puede borrar).
     */
    public static function isReferenced(AgeRange $range): bool
    {
        $id = $range->getKey();

        foreach (['fees', 'afilliation_corporate_plans', 'afilliation_individual_plans', 'affiliates', 'detail_corporate_quotes', 'detail_individual_quotes', 'corporate_quote_request_data', 'renovations', 'renovation_corporates', 'affiliation_renovation_histories', 'affiliation_corporate_renovation_histories', 'plan_generator_rate_rows', 'check_affiliations'] as $table) {
            if (DB::table($table)->where('age_range_id', $id)->exists()) {
                return true;
            }
        }

        return false;
    }

    public static function label(int $init, int $end): string
    {
        return $init.' a '.$end;
    }

    /**
     * Qué pasaría si el rango pasa a [$init, $end], sin escribir nada.
     *
     * @return array{
     *     blocking: list<string>,
     *     changed: bool,
     *     usage: list<array{label: string, count: int, detail: string|null}>,
     *     left_out: list<string>,
     *     left_out_total: int,
     *     newly_covered: int,
     *     text_copies: int
     * }
     */
    public static function impact(Plan $plan, AgeRange $range, int $init, int $end): array
    {
        $blocking = [];

        if ($init < 0 || $end > self::MAX_AGE || $init > $end) {
            $blocking[] = 'Las edades deben estar entre 0 y '.self::MAX_AGE.' y la edad inicial no puede ser mayor que la final.';
        }

        $otherPlans = self::otherPlans($plan, $range);
        if ($otherPlans !== []) {
            $blocking[] = 'Este rango también lo usan otros planes ('.implode(', ', $otherPlans).'). Cambiarlo movería sus tarifas. Cree un rango propio para este plan desde su edición.';
        }

        foreach (self::overlaps($plan, $range, $init, $end) as $overlap) {
            $blocking[] = 'Se cruzaría con el rango «'.$overlap->range.'» ('.$overlap->age_init.' a '.$overlap->age_end.' años) del mismo plan: una misma edad tendría dos tarifas.';
        }

        $leftOut = $blocking === [] ? self::leftOut($range, $init, $end) : ['people' => [], 'total' => 0, 'newly' => 0];
        if ($leftOut['total'] > 0) {
            $blocking[] = $leftOut['total'].' afiliado(s) o renovación(es) vigentes quedarían sin rango de edad y sin tarifa. Ajuste las edades o resuelva primero esos casos.';
        }

        return [
            'blocking' => $blocking,
            'changed' => (int) $range->age_init !== $init || (int) $range->age_end !== $end || (string) $range->range !== self::label($init, $end),
            'usage' => self::usage($range),
            'left_out' => $leftOut['people'],
            'left_out_total' => $leftOut['total'],
            'newly_covered' => $leftOut['newly'],
            'text_copies' => self::textCopies($range)->sum(),
        ];
    }

    /**
     * Aplica el cambio: todo junto o nada, con el rango bloqueado.
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>, copies: array<string, int>}
     */
    public static function apply(Plan $plan, AgeRange $range, int $init, int $end, string $reason, User $user): array
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique el motivo del cambio (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        $result = DB::transaction(static function () use ($plan, $range, $init, $end, $reason): array {
            $locked = AgeRange::query()->lockForUpdate()->findOrFail($range->getKey());

            $impact = self::impact($plan, $locked, $init, $end);
            if ($impact['blocking'] !== []) {
                throw new RuntimeException(implode(' ', $impact['blocking']));
            }

            if (! $impact['changed']) {
                throw new RuntimeException('El rango ya tiene esas edades: no hay nada que cambiar.');
            }

            $label = self::label($init, $end);
            $before = ['age_init' => $locked->age_init, 'age_end' => $locked->age_end, 'range' => $locked->range];

            $locked->forceFill(['age_init' => $init, 'age_end' => $end, 'range' => $label])->save();

            $feeIds = Fee::query()->where('age_range_id', $locked->getKey())->pluck('id');
            $copies = [
                'fees' => 0,
                'fee_plans' => DB::table('fee_plans')->whereIn('fee_id', $feeIds->all() ?: [0])->where('range', '!=', $label)->update(['range' => $label, 'updated_at' => now()]),
                'plan_generator_rate_rows' => DB::table('plan_generator_rate_rows')->where('age_range_id', $locked->getKey())->where('age_range_label', '!=', $label)->update(['age_range_label' => $label, 'updated_at' => now()]),
            ];

            Fee::query()->whereKey($feeIds->all())->get()->each(static function (Fee $fee) use ($label, &$copies): void {
                if ((string) $fee->range !== $label) {
                    $fee->range = $label;
                    $fee->save();
                    $copies['fees']++;
                }
            });

            return [
                'before' => $before,
                'after' => ['age_init' => $init, 'age_end' => $end, 'range' => $label],
                'copies' => $copies,
                'reason' => $reason,
                'newly_covered' => $impact['newly_covered'],
            ];
        });

        SecurityAudit::log('AUDIT_PLAN_AGE_RANGE_EDITED', 'business.plans.age-range', [
            'plan_id' => $plan->getKey(),
            'plan_code' => $plan->code,
            'age_range_id' => $range->getKey(),
            'before' => $result['before'],
            'after' => $result['after'],
            'text_copies_updated' => $result['copies'],
            'newly_covered' => $result['newly_covered'],
            'reason' => $result['reason'],
        ], $user);

        return ['before' => $result['before'], 'after' => $result['after'], 'copies' => $result['copies']];
    }

    /**
     * @return list<string> códigos de otros planes que usan el rango
     */
    private static function otherPlans(Plan $plan, AgeRange $range): array
    {
        $planIds = collect()
            ->merge(Fee::query()->where('age_range_id', $range->getKey())->pluck('plan_id'))
            ->merge(AfilliationCorporatePlan::query()->where('age_range_id', $range->getKey())->pluck('plan_id'))
            ->merge(DB::table('afilliation_individual_plans')->where('age_range_id', $range->getKey())->pluck('plan_id'))
            ->merge(DB::table('detail_individual_quotes')->where('age_range_id', $range->getKey())->pluck('plan_id'))
            ->merge(DB::table('detail_corporate_quotes')->where('age_range_id', $range->getKey())->pluck('plan_id'))
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->reject(static fn (int $id): bool => $id === (int) $plan->getKey())
            ->unique();

        if ($planIds->isEmpty()) {
            return [];
        }

        return Plan::query()->whereIn('id', $planIds->all())->get(['id', 'code', 'description'])
            ->map(static fn (Plan $other): string => trim($other->code.' '.$other->description))
            ->values()
            ->all();
    }

    /**
     * Rangos activos del mismo plan, en las mismas coberturas, que se cruzarían.
     *
     * @return Collection<int, AgeRange>
     */
    private static function overlaps(Plan $plan, AgeRange $range, int $init, int $end): Collection
    {
        $coverages = Fee::query()->where('plan_id', $plan->getKey())->where('age_range_id', $range->getKey())->pluck('coverage_id');
        $withNull = $coverages->contains(null);
        $coverageIds = $coverages->filter()->all();

        $siblingIds = Fee::query()
            ->where('plan_id', $plan->getKey())
            ->where('age_range_id', '!=', $range->getKey())
            ->where(static fn ($query) => $query->where('status', 'ACTIVO')->orWhereNull('status'))
            ->where(static function ($query) use ($withNull, $coverageIds): void {
                if ($withNull) {
                    $query->orWhereNull('coverage_id');
                }

                if ($coverageIds !== []) {
                    $query->orWhereIn('coverage_id', $coverageIds);
                }
            })
            ->pluck('age_range_id');

        return AgeRange::query()
            ->whereIn('id', $siblingIds->all() ?: [0])
            ->whereNotNull('age_init')
            ->whereNotNull('age_end')
            ->where('age_init', '<=', $end)
            ->where('age_end', '>=', $init)
            ->get();
    }

    /**
     * Afiliados vigentes y renovaciones que hoy caen en el rango y dejarían de caer
     * en alguno. En corporativas se simula la resolución completa de la fila de plan
     * (la misma que usan altas y renovaciones), así cuenta también la ambigüedad.
     *
     * @return array{people: list<string>, total: int, newly: int}
     */
    private static function leftOut(AgeRange $range, int $init, int $end): array
    {
        $people = [];
        $total = 0;
        $newly = 0;
        $simulated = (clone $range)->forceFill(['age_init' => $init, 'age_end' => $end]);

        $affiliationIds = AfilliationCorporatePlan::query()->where('age_range_id', $range->getKey())->distinct()->pluck('affiliation_corporate_id');

        foreach (AffiliationCorporate::query()->whereIn('id', $affiliationIds->all() ?: [0])->get() as $owner) {
            $rowsNow = CorporateAffiliatePlanSynchronizer::planRowsFor($owner);
            $rowsAfter = $rowsNow->map(static function (AfilliationCorporatePlan $row) use ($range, $simulated): AfilliationCorporatePlan {
                if ((int) $row->age_range_id !== (int) $range->getKey()) {
                    return $row;
                }

                $copy = clone $row;
                $copy->setRelation('ageRange', $simulated);

                return $copy;
            });

            AffiliateCorporate::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->get(['id', 'first_name', 'last_name', 'nro_identificacion', 'age', 'plan_id', 'status'])
                ->each(static function (AffiliateCorporate $affiliate) use ($owner, $rowsNow, $rowsAfter, &$people, &$total, &$newly): void {
                    $before = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $affiliate, $rowsNow)['row'];
                    $after = CorporateAffiliatePlanSynchronizer::resolvePlanRowForAffiliate($owner, $affiliate, $rowsAfter)['row'];

                    if ($before !== null && $after === null) {
                        $total++;
                        if (count($people) < self::LIST_LIMIT) {
                            $people[] = $owner->code.' · '.trim($affiliate->first_name.' '.$affiliate->last_name).' (C.I. '.$affiliate->nro_identificacion.', '.$affiliate->age.' años)';
                        }
                    } elseif ($before === null && $after !== null) {
                        $newly++;
                    }
                });
        }

        $outside = static fn ($query) => $query->whereNotNull('age')->where(static fn ($q) => $q->where('age', '<', $init)->orWhere('age', '>', $end));

        $individualRows = DB::table('afilliation_individual_plans')
            ->whereIn('affiliation_individual_id', DB::table('afilliation_individual_plans')->where('age_range_id', $range->getKey())->pluck('affiliation_individual_id')->all() ?: [0])
            ->get(['affiliation_individual_id', 'age_range_id']);
        $ranges = AgeRange::query()->whereIn('id', $individualRows->pluck('age_range_id')->filter()->unique()->all() ?: [0])->get()->keyBy('id');
        $coveredElsewhere = static function (int $affiliationId, int $age) use ($individualRows, $ranges, $range): bool {
            foreach ($individualRows->where('affiliation_individual_id', $affiliationId) as $row) {
                $other = $ranges->get((int) $row->age_range_id);
                if ($other instanceof AgeRange && (int) $other->getKey() !== (int) $range->getKey() && $age >= (int) $other->age_init && $age <= (int) $other->age_end) {
                    return true;
                }
            }

            return false;
        };

        DB::table('affiliates')
            ->whereNull('deleted_at')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where(static fn ($query) => $query->where('age_range_id', $range->getKey())->orWhereIn('affiliation_id', $individualRows->pluck('affiliation_individual_id')->unique()->all() ?: [0]))
            ->where($outside)
            ->orderBy('id')
            ->get(['affiliation_id', 'full_name', 'nro_identificacion', 'age'])
            ->each(static function (object $affiliate) use (&$people, &$total, $coveredElsewhere, $init, $end, $range): void {
                $age = (int) $affiliate->age;
                $wasInside = $age >= (int) $range->age_init && $age <= (int) $range->age_end;
                if (! $wasInside || $coveredElsewhere((int) $affiliate->affiliation_id, $age) || ($age >= $init && $age <= $end)) {
                    return;
                }

                $total++;
                if (count($people) < self::LIST_LIMIT) {
                    $people[] = 'Individual · '.$affiliate->full_name.' (C.I. '.$affiliate->nro_identificacion.', '.$age.' años)';
                }
            });

        foreach (['renovations' => 'Renovación individual', 'renovation_corporates' => 'Renovación corporativa'] as $table => $label) {
            DB::table($table)
                ->where('age_range_id', $range->getKey())
                ->whereBetween('age', [(int) $range->age_init, (int) $range->age_end])
                ->where($outside)
                ->get(['code_affiliation', 'age'])
                ->each(static function (object $row) use ($label, &$people, &$total): void {
                    $total++;
                    if (count($people) < self::LIST_LIMIT) {
                        $people[] = $label.' · '.$row->code_affiliation.' ('.$row->age.' años)';
                    }
                });
        }

        return ['people' => $people, 'total' => $total, 'newly' => $newly];
    }

    /**
     * @return list<array{label: string, count: int, detail: string|null}>
     */
    private static function usage(AgeRange $range): array
    {
        $id = $range->getKey();
        $corporateCodes = AffiliationCorporate::query()
            ->whereIn('id', AfilliationCorporatePlan::query()->where('age_range_id', $id)->pluck('affiliation_corporate_id')->all() ?: [0])
            ->pluck('code');

        $rows = [
            ['label' => 'Tarifas del plan', 'count' => Fee::query()->where('age_range_id', $id)->count(), 'detail' => null],
            ['label' => 'Afiliaciones corporativas', 'count' => $corporateCodes->count(), 'detail' => $corporateCodes->take(10)->implode(', ') ?: null],
            ['label' => 'Afiliaciones individuales', 'count' => DB::table('afilliation_individual_plans')->where('age_range_id', $id)->distinct()->count('affiliation_individual_id'), 'detail' => null],
            ['label' => 'Cotizaciones corporativas', 'count' => DB::table('detail_corporate_quotes')->where('age_range_id', $id)->distinct()->count('corporate_quote_id'), 'detail' => null],
            ['label' => 'Cotizaciones individuales', 'count' => DB::table('detail_individual_quotes')->where('age_range_id', $id)->distinct()->count('individual_quote_id'), 'detail' => null],
            ['label' => 'Renovaciones preparadas', 'count' => DB::table('renovations')->where('age_range_id', $id)->count() + DB::table('renovation_corporates')->where('age_range_id', $id)->count(), 'detail' => null],
            ['label' => 'Históricos de renovación', 'count' => DB::table('affiliation_renovation_histories')->where('age_range_id', $id)->count() + DB::table('affiliation_corporate_renovation_histories')->where('age_range_id', $id)->count(), 'detail' => 'Muestran el rango por su número: verán las edades nuevas.'],
            ['label' => 'Generador de planes', 'count' => DB::table('plan_generator_rate_rows')->where('age_range_id', $id)->count(), 'detail' => null],
        ];

        return array_values(array_filter($rows, static fn (array $row): bool => $row['count'] > 0));
    }

    /**
     * Textos copiados del rango que se actualizan al guardar.
     *
     * @return Collection<string, int>
     */
    private static function textCopies(AgeRange $range): Collection
    {
        $feeIds = Fee::query()->where('age_range_id', $range->getKey())->pluck('id');

        return collect([
            'fees' => $feeIds->count(),
            'fee_plans' => DB::table('fee_plans')->whereIn('fee_id', $feeIds->all() ?: [0])->count(),
            'plan_generator_rate_rows' => DB::table('plan_generator_rate_rows')->where('age_range_id', $range->getKey())->count(),
        ]);
    }
}

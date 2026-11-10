<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Models\QuoteDeletionLog;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cotizaciones por año y por plan (Comercial · panel de Métricas).
 *
 * Reglas, verificadas contra los datos reales (09/11/2026):
 *
 * - **Plan de una cotización** (individual y corporativa). Si declara un solo plan (`plan` = 1,
 *   2 o 3), ese es su plan: en individuales hay 178 sin detalle y 14 con un detalle de otro plan.
 *   Las múltiples («CM») y las que no tienen plan marcado se resuelven por los planes de su
 *   detalle: una múltiple con tres planes cuenta en los tres. Las que no tienen ningún plan
 *   1, 2 o 3 (Dress Tylor, planes cotizables) no entran en ninguno y se informan aparte.
 * - **Creadas**: por `created_at` en el período, más las borradas que la bitácora
 *   (`quote_deletion_logs`) registra como creadas en ese período.
 * - Cada creada cae en **una sola** categoría, así las partes suman el total:
 *   1. **Emitida**: tiene una afiliación vigente (no eliminada) de ese plan, por el vínculo real
 *      (individual: `affiliations.individual_quote_id` + `plan_id`; corporativa:
 *      `affiliation_corporates.corporate_quote_id` + un plan de la afiliación). No se usa el
 *      estado EJECUTADA, que no siempre coincide.
 *   2. **Anulada**: estado ANULADA y sin afiliación de ese plan. Las corporativas no se anulan.
 *   3. **Eliminada**: borrada (solo desde el 09/11/2026; antes no quedaba rastro).
 *   4. **En curso**: el resto, separada en aprobadas sin afiliación y pre-aprobadas.
 * - **Año**: del 1 de enero a hoy contra el mismo tramo del año anterior; además, el total del
 *   año anterior completo.
 */
final class CorporateQuoteMetrics
{
    public const INITIAL_PLAN_ID = 1;

    public const IDEAL_PLAN_ID = 2;

    public const SPECIAL_PLAN_ID = 3;

    public const INDIVIDUAL = 'individual';

    public const CORPORATE = 'corporate';

    public const SINGLE_PLAN_CODES = ['1', '2', '3'];

    /** Estados que significan que el cliente ya aprobó y falta afiliar. */
    public const APPROVED_STATUSES = ['APROBADA', 'EJECUTADA', 'ACTIVA-PENDIENTE'];

    public const PRE_APPROVED_STATUS = 'PRE-APROBADA';

    public const ANULATED_STATUS = 'ANULADA';

    /** Día desde el que existe la bitácora de cotizaciones borradas. */
    public const DELETION_TRACKING_SINCE = '2026-11-09';

    /** Días tras los que el sistema anula sola una cotización individual sin aprobar. */
    public const AUTO_ANULATION_DAYS = 15;

    /**
     * @return array<int, string>
     */
    public static function plans(): array
    {
        return [
            self::INITIAL_PLAN_ID => 'Plan Inicial',
            self::IDEAL_PLAN_ID => 'Plan Ideal',
            self::SPECIAL_PLAN_ID => 'Plan Especial',
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     from: string,
     *     to: string,
     *     previous_from: string,
     *     previous_to: string,
     *     current: array{individual: int, corporate: int, total: int},
     *     previous: array{individual: int, corporate: int, total: int},
     *     previous_full_year: array{individual: int, corporate: int, total: int}
     * }
     */
    public static function yearToDate(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        return CorporateMetrics::remember('commercial_quotes_ytd_'.$today->toDateString(), null, static function () use ($today): array {
            $from = $today->startOfYear();
            $previousFrom = $from->subYear();
            $previousTo = $today->subYearNoOverflow();

            return [
                'year' => $today->year,
                'from' => $from->toDateString(),
                'to' => $today->toDateString(),
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
                'current' => self::createdBetween($from, $today),
                'previous' => self::createdBetween($previousFrom, $previousTo),
                'previous_full_year' => self::createdBetween($previousFrom, $previousFrom->endOfYear()),
            ];
        });
    }

    /**
     * Cifras de un plan y tipo: período actual y anterior.
     *
     * @return array{current: array<string, int>, previous: array<string, int>}
     */
    public static function plan(Period $period, int $planId, string $type = self::INDIVIDUAL): array
    {
        return CorporateMetrics::remember('commercial_quotes_plan_'.$type.'_'.$planId, $period, static fn (): array => [
            'current' => self::planBetween($planId, $period->from, $period->to, $type),
            'previous' => self::planBetween($planId, $period->previousFrom, $period->previousTo, $type),
        ]);
    }

    /**
     * Cotizaciones del período que no se pueden atribuir a Inicial, Ideal ni Especial.
     *
     * @return array{individual: int, corporate: int}
     */
    public static function unattributed(Period $period): array
    {
        return CorporateMetrics::remember('commercial_quotes_unattributed', $period, static fn (): array => [
            self::INDIVIDUAL => self::unattributedBetween(self::INDIVIDUAL, $period->from, $period->to),
            self::CORPORATE => self::unattributedBetween(self::CORPORATE, $period->from, $period->to),
        ]);
    }

    /**
     * Partición de las cotizaciones creadas en el rango para un plan: cada una en una sola
     * categoría (`issued` + `anulated` + `deleted` + `open` = `created`).
     *
     * `stale` son pre-aprobadas individuales con más de 15 días: el sistema debió anularlas
     * (la tarea nocturna está apagada en el Centro de notificaciones o no corrió).
     *
     * @return array{created: int, issued: int, anulated: int, deleted: int, voided: int, open: int, approved: int, pre_approved: int, other_open: int, stale: int}
     */
    public static function planBetween(int $planId, CarbonImmutable $from, CarbonImmutable $to, string $type = self::INDIVIDUAL): array
    {
        $rows = self::quotesWithIssuance($type, $planId, $from, $to);

        $out = ['created' => 0, 'issued' => 0, 'anulated' => 0, 'deleted' => 0, 'voided' => 0, 'open' => 0, 'approved' => 0, 'pre_approved' => 0, 'other_open' => 0, 'stale' => 0];
        $staleBefore = CarbonImmutable::today()->subDays(self::AUTO_ANULATION_DAYS)->toDateTimeString();

        foreach ($rows as $row) {
            $out['created']++;
            $status = strtoupper(trim((string) $row->status));

            if ((int) $row->issued === 1) {
                $out['issued']++;
            } elseif ($status === self::ANULATED_STATUS) {
                $out['anulated']++;
            } elseif (in_array($status, self::APPROVED_STATUSES, true)) {
                $out['approved']++;
            } elseif ($status === self::PRE_APPROVED_STATUS) {
                $out['pre_approved']++;

                if ($type === self::INDIVIDUAL && (string) $row->created_at < $staleBefore) {
                    $out['stale']++;
                }
            } else {
                $out['other_open']++;
            }
        }

        $out['deleted'] = self::deletedBetween($type, $from, $to)
            ->filter(fn (QuoteDeletionLog $log): bool => self::deletedLogHasPlan($log, $planId))
            ->count();

        $out['created'] += $out['deleted'];
        $out['voided'] = $out['anulated'] + $out['deleted'];
        $out['open'] = $out['approved'] + $out['pre_approved'] + $out['other_open'];

        return $out;
    }

    /**
     * @return array{individual: int, corporate: int, total: int}
     */
    public static function createdBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = CorporateMetrics::range($from, $to);

        $deleted = QuoteDeletionLog::query()
            ->whereBetween('quote_created_at', $range)
            ->selectRaw('quote_type, COUNT(*) as total')
            ->groupBy('quote_type')
            ->pluck('total', 'quote_type');

        $individual = DB::table('individual_quotes')->whereBetween('created_at', $range)->count()
            + (int) ($deleted[QuoteDeletionLog::INDIVIDUAL] ?? 0);
        $corporate = DB::table('corporate_quotes')->whereBetween('created_at', $range)->count()
            + (int) ($deleted[QuoteDeletionLog::CORPORATE] ?? 0);

        return [
            'individual' => $individual,
            'corporate' => $corporate,
            'total' => $individual + $corporate,
        ];
    }

    /**
     * Pertenencia de una cotización a un plan. `$alias` es la tabla de la cotización en la
     * consulta (q individual, c corporativa).
     */
    public static function wherePlan(Builder $query, int $planId, string $type = self::INDIVIDUAL, string $alias = 'q'): void
    {
        [$detailTable, $detailKey] = self::detailTable($type);

        $query->where("{$alias}.plan", (string) $planId)
            ->orWhere(function (Builder $multiple) use ($planId, $alias, $detailTable, $detailKey): void {
                $multiple->where(function (Builder $noSinglePlan) use ($alias): void {
                    $noSinglePlan->whereNull("{$alias}.plan")->orWhereNotIn("{$alias}.plan", self::SINGLE_PLAN_CODES);
                })->whereExists(function (Builder $detail) use ($planId, $alias, $detailTable, $detailKey): void {
                    $detail->selectRaw('1')
                        ->from("{$detailTable} as d")
                        ->whereColumn("d.{$detailKey}", "{$alias}.id")
                        ->where('d.plan_id', $planId);
                });
            });
    }

    public static function deletedLogHasPlan(QuoteDeletionLog $log, int $planId): bool
    {
        $plan = trim((string) $log->plan);

        if (in_array($plan, self::SINGLE_PLAN_CODES, true)) {
            return $plan === (string) $planId;
        }

        return in_array($planId, array_map('intval', $log->plan_ids ?? []), true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id: int, status: ?string, created_at: ?string, issued: int}>
     */
    private static function quotesWithIssuance(string $type, int $planId, CarbonImmutable $from, CarbonImmutable $to): \Illuminate\Support\Collection
    {
        $alias = $type === self::CORPORATE ? 'c' : 'q';
        $table = $type === self::CORPORATE ? 'corporate_quotes' : 'individual_quotes';

        $issuedSql = $type === self::CORPORATE
            ? 'EXISTS (SELECT 1 FROM affiliation_corporates ac JOIN afilliation_corporate_plans acp ON acp.affiliation_corporate_id = ac.id WHERE ac.corporate_quote_id = c.id AND ac.deleted_at IS NULL AND acp.plan_id = ?)'
            : 'EXISTS (SELECT 1 FROM affiliations a WHERE a.individual_quote_id = q.id AND a.plan_id = ? AND a.deleted_at IS NULL)';

        return DB::table("{$table} as {$alias}")
            ->whereBetween("{$alias}.created_at", CorporateMetrics::range($from, $to))
            ->where(fn (Builder $query) => self::wherePlan($query, $planId, $type, $alias))
            ->select("{$alias}.id", "{$alias}.status", "{$alias}.created_at")
            ->selectRaw("CASE WHEN {$issuedSql} THEN 1 ELSE 0 END as issued", [$planId])
            ->get();
    }

    private static function unattributedBetween(string $type, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $alias = $type === self::CORPORATE ? 'c' : 'q';
        $table = $type === self::CORPORATE ? 'corporate_quotes' : 'individual_quotes';
        [$detailTable, $detailKey] = self::detailTable($type);

        return DB::table("{$table} as {$alias}")
            ->whereBetween("{$alias}.created_at", CorporateMetrics::range($from, $to))
            ->where(function (Builder $query) use ($alias): void {
                $query->whereNull("{$alias}.plan")->orWhereNotIn("{$alias}.plan", self::SINGLE_PLAN_CODES);
            })
            ->whereNotExists(function (Builder $query) use ($alias, $detailTable, $detailKey): void {
                $query->selectRaw('1')
                    ->from("{$detailTable} as d")
                    ->whereColumn("d.{$detailKey}", "{$alias}.id")
                    ->whereIn('d.plan_id', array_keys(self::plans()));
            })
            ->count();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function detailTable(string $type): array
    {
        return $type === self::CORPORATE
            ? ['detail_corporate_quotes', 'corporate_quote_id']
            : ['detail_individual_quotes', 'individual_quote_id'];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, QuoteDeletionLog>
     */
    private static function deletedBetween(string $type, CarbonImmutable $from, CarbonImmutable $to): \Illuminate\Database\Eloquent\Collection
    {
        return QuoteDeletionLog::query()
            ->where('quote_type', $type === self::CORPORATE ? QuoteDeletionLog::CORPORATE : QuoteDeletionLog::INDIVIDUAL)
            ->whereBetween('quote_created_at', CorporateMetrics::range($from, $to))
            ->get(['id', 'plan', 'plan_ids']);
    }
}

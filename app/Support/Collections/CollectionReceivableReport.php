<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reporte de cuentas por cobrar (CXC) de «Cobranza por mes».
 *
 * Sale **solo de las cuotas reales** (`collections`): una fila por afiliación, que es
 * su próxima cuota pendiente (POR PAGAR con el vencimiento más cercano). No lee
 * `annual_collections`, que no tiene fila para todas las afiliaciones y guarda copias
 * de fechas y días que se desincronizan.
 *
 * La fecha y los días vienen de {@see CollectionDueDate}, la misma clase que usa
 * «Gestión de Cobranza», así que las dos tablas siempre coinciden.
 */
final class CollectionReceivableReport
{
    public const PENDING_STATUS = CollectionDueDate::PENDING_STATUS;

    public const STATUS_PENDING = CollectionDueDate::PENDING_STATUS;

    public const STATUS_OVERDUE = CollectionDueDate::STATUS_OVERDUE;

    /**
     * Afiliaciones que ya no se cobran: no entran en la tabla, el CSV ni el
     * resumen de cuentas por cobrar. Sus cuotas siguen en la base; si la
     * afiliación se reactiva, vuelve a aparecer.
     *
     * @var list<string>
     */
    public const EXCLUDED_AFFILIATION_STATUSES = ['EXCLUIDO', 'EXCLUIDA', 'INACTIVO', 'INACTIVA', 'ANULADO', 'ANULADA'];

    public const AGING_DUE_SOON = 'vence_7';

    public const AGING_DUE_30 = 'vence_30';

    public const AGING_DUE_45 = 'vence_45';

    public const AGING_DUE_60 = 'vence_60';

    /**
     * Plazos de «Total por cobrar»: lo que vence de hoy a N días. Son
     * acumulados (el de 45 incluye el de 30) y cada uno es también un filtro.
     *
     * @var array<int, string> Días => clave del filtro «Vencimiento».
     */
    public const DUE_WINDOWS = [
        30 => self::AGING_DUE_30,
        45 => self::AGING_DUE_45,
        60 => self::AGING_DUE_60,
    ];

    public const AGING_NOT_DUE = 'por_vencer';

    public const AGING_OVERDUE_1_30 = 'vencido_1_30';

    public const AGING_OVERDUE_31_60 = 'vencido_31_60';

    public const AGING_OVERDUE_61_90 = 'vencido_61_90';

    public const AGING_OVERDUE_90_PLUS = 'vencido_90';

    /**
     * Relaciones que necesita cada fila; cargarlas juntas evita N+1.
     *
     * @return list<string>
     */
    public static function eagerLoads(): array
    {
        return [
            'plan:id,description',
            'agent:id,name',
            'agencyByCode:id,code,name_corporative',
            'affiliationByCode:id,code,status,fee_anual,effective_date,payment_frequency,full_name_payer,nro_identificacion_payer',
            'affiliationCorporateByCode:id,code,status,fee_anual,effective_date,payment_frequency,name_corporate,rif',
            'affiliationCorporateByCode.affiliationCorporatePlans:id,affiliation_corporate_id,plan_id',
            'affiliationCorporateByCode.affiliationCorporatePlans.plan:id,description',
        ];
    }

    /**
     * Una fila por afiliación cobrable: su cuota pendiente de vencimiento más
     * cercano. Deja fuera las afiliaciones excluidas, inactivas o anuladas.
     */
    public static function scopeNextPendingPerAffiliation(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $pending = self::PENDING_STATUS;

        return self::excludeNonCollectableAffiliations($query)
            ->where("{$table}.status", $pending)
            ->whereRaw(
                "{$table}.id = (select next_installment.id from collections as next_installment"
                ." where next_installment.affiliation_code = {$table}.affiliation_code"
                .' and next_installment.status = ?'
                .' order by next_installment.filter_next_payment_date asc, next_installment.id asc limit 1)',
                [$pending],
            );
    }

    /**
     * Quita las cuotas de afiliaciones excluidas, inactivas o anuladas según el
     * estatus **actual** de la afiliación (individual o corporativa): el que se
     * copió en la cuota se queda viejo cuando la afiliación cambia después. Solo
     * si la afiliación ya no existe se usa el estatus copiado.
     */
    public static function excludeNonCollectableAffiliations(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $placeholders = implode(', ', array_fill(0, count(self::EXCLUDED_AFFILIATION_STATUSES), '?'));
        $excluded = fn (Builder $affiliation): Builder => $affiliation->whereRaw("UPPER(TRIM(status)) in ({$placeholders})", self::EXCLUDED_AFFILIATION_STATUSES);

        return $query
            ->whereDoesntHave('affiliationByCode', $excluded)
            ->whereDoesntHave('affiliationCorporateByCode', $excluded)
            ->where(function (Builder $query) use ($table, $placeholders): void {
                $query->whereHas('affiliationByCode')
                    ->orWhereHas('affiliationCorporateByCode')
                    ->orWhereNull("{$table}.affiliate_status")
                    ->orWhereRaw("UPPER(TRIM({$table}.affiliate_status)) not in ({$placeholders})", self::EXCLUDED_AFFILIATION_STATUSES);
            });
    }

    /**
     * @return array<string, string>
     */
    public static function agingOptions(): array
    {
        return [
            self::AGING_DUE_SOON => 'Vence en los próximos 7 días',
            self::AGING_DUE_30 => 'Vence en los próximos 30 días',
            self::AGING_DUE_45 => 'Vence en los próximos 45 días',
            self::AGING_DUE_60 => 'Vence en los próximos 60 días',
            self::AGING_NOT_DUE => 'Por vencer (todas)',
            self::AGING_OVERDUE_1_30 => 'Vencidas de 1 a 30 días',
            self::AGING_OVERDUE_31_60 => 'Vencidas de 31 a 60 días',
            self::AGING_OVERDUE_61_90 => 'Vencidas de 61 a 90 días',
            self::AGING_OVERDUE_90_PLUS => 'Vencidas hace más de 90 días',
        ];
    }

    public static function applyAging(Builder $query, ?string $bucket, ?CarbonImmutable $today = null): Builder
    {
        $today ??= CarbonImmutable::today();
        $column = $query->getModel()->getTable().'.filter_next_payment_date';

        return match ($bucket) {
            self::AGING_DUE_SOON => $query->whereBetween($column, [$today->toDateString(), $today->addDays(7)->toDateString()]),
            self::AGING_DUE_30 => $query->whereBetween($column, [$today->toDateString(), $today->addDays(30)->toDateString()]),
            self::AGING_DUE_45 => $query->whereBetween($column, [$today->toDateString(), $today->addDays(45)->toDateString()]),
            self::AGING_DUE_60 => $query->whereBetween($column, [$today->toDateString(), $today->addDays(60)->toDateString()]),
            self::AGING_NOT_DUE => $query->where($column, '>=', $today->toDateString()),
            self::AGING_OVERDUE_1_30 => $query->whereBetween($column, [$today->subDays(30)->toDateString(), $today->subDay()->toDateString()]),
            self::AGING_OVERDUE_31_60 => $query->whereBetween($column, [$today->subDays(60)->toDateString(), $today->subDays(31)->toDateString()]),
            self::AGING_OVERDUE_61_90 => $query->whereBetween($column, [$today->subDays(90)->toDateString(), $today->subDays(61)->toDateString()]),
            self::AGING_OVERDUE_90_PLUS => $query->where($column, '<', $today->subDays(90)->toDateString()),
            default => $query,
        };
    }

    public static function applyCollectionStatus(Builder $query, ?string $status, ?CarbonImmutable $today = null): Builder
    {
        $today ??= CarbonImmutable::today();
        $column = $query->getModel()->getTable().'.filter_next_payment_date';

        return match ($status) {
            self::STATUS_OVERDUE => $query->where($column, '<', $today->toDateString()),
            self::STATUS_PENDING => $query->where($column, '>=', $today->toDateString()),
            default => $query,
        };
    }

    public static function applyDueBetween(Builder $query, ?string $from, ?string $until): Builder
    {
        $column = $query->getModel()->getTable().'.filter_next_payment_date';

        return $query
            ->when($from, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
            ->when($until, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date));
    }

    /**
     * Filtra por el estatus vigente de la afiliación (individual o corporativa). Si la
     * afiliación ya no existe se usa el estatus copiado en la cuota.
     *
     * @param  list<string>  $statuses
     */
    public static function applyAffiliateStatus(Builder $query, array $statuses): Builder
    {
        $statuses = array_values(array_filter(array_map(fn (mixed $status): string => Str::upper(trim((string) $status)), $statuses)));

        if ($statuses === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($statuses): void {
            $query
                ->whereHas('affiliationByCode', fn (Builder $affiliation): Builder => $affiliation->whereIn('status', $statuses))
                ->orWhereHas('affiliationCorporateByCode', fn (Builder $affiliation): Builder => $affiliation->whereIn('status', $statuses))
                ->orWhere(fn (Builder $orphan): Builder => $orphan
                    ->whereDoesntHave('affiliationByCode')
                    ->whereDoesntHave('affiliationCorporateByCode')
                    ->whereIn('affiliate_status', $statuses));
        });
    }

    public static function dueDate(Collection $collection): ?CarbonImmutable
    {
        return CollectionDueDate::of($collection);
    }

    public static function collectionStatus(Collection $collection, ?CarbonImmutable $today = null): string
    {
        return CollectionDueDate::collectionStatus($collection, $today);
    }

    public static function daysCount(Collection $collection, ?CarbonImmutable $today = null): ?int
    {
        return CollectionDueDate::daysCount($collection, $today);
    }

    public static function daysLabel(Collection $collection, ?CarbonImmutable $today = null): string
    {
        return CollectionDueDate::daysLabel($collection, $today) ?? 'Sin fecha';
    }

    public static function paymentFrequency(Collection $collection): ?string
    {
        $frequency = self::clean($collection->payment_frequency)
            ?? self::clean(self::isCorporate($collection)
                ? self::corporateAffiliation($collection)?->payment_frequency
                : self::individualAffiliation($collection)?->payment_frequency);

        return $frequency === null ? null : Str::upper($frequency);
    }

    public static function installmentsPerYear(?string $frequency): ?int
    {
        return match (Str::upper(trim((string) $frequency))) {
            'ANUAL' => 1,
            'SEMESTRAL' => 2,
            'TRIMESTRAL' => 4,
            'MENSUAL' => 12,
            default => null,
        };
    }

    /**
     * Número de la cuota dentro del año de contrato, p. ej. «2 de 4».
     *
     * Las cuotas de un año se crean juntas con la misma `include_date` (inicio del
     * período), así que el número sale de los meses entre esa fecha y el vencimiento.
     * Si una renovación dejó la `include_date` del año anterior, el resultado se lleva
     * al año de contrato que corresponde.
     */
    public static function installmentLabel(Collection $collection): ?string
    {
        $perYear = self::installmentsPerYear(self::paymentFrequency($collection));
        $due = self::dueDate($collection);
        $start = CollectionDueDate::parse($collection->include_date);

        if ($perYear === null || $due === null || $start === null) {
            return null;
        }

        if ($perYear === 1) {
            return '1 de 1';
        }

        $monthsPerInstallment = intdiv(12, $perYear);
        $elapsedMonths = max((int) round($start->diffInMonths($due, false)), 0) % 12;
        $number = intdiv($elapsedMonths, $monthsPerInstallment) + 1;

        return min($number, $perYear).' de '.$perYear;
    }

    public static function installmentAmount(Collection $collection): ?float
    {
        return is_numeric($collection->total_amount) ? (float) $collection->total_amount : null;
    }

    public static function isCorporate(Collection $collection): bool
    {
        return str_contains(Str::upper(Str::ascii((string) $collection->type)), 'CORPORATIVA');
    }

    public static function holderName(Collection $collection): ?string
    {
        return self::clean($collection->affiliate_full_name);
    }

    public static function holderDocument(Collection $collection): ?string
    {
        return self::clean($collection->affiliate_ci_rif);
    }

    /**
     * Tomador = quien paga. En individuales es el pagador de la afiliación; en
     * corporativas es la empresa contratante.
     */
    public static function payerName(Collection $collection): ?string
    {
        if (self::isCorporate($collection)) {
            return self::clean(self::corporateAffiliation($collection)?->name_corporate) ?? self::holderName($collection);
        }

        return self::clean(self::individualAffiliation($collection)?->full_name_payer) ?? self::holderName($collection);
    }

    public static function payerDocument(Collection $collection): ?string
    {
        if (self::isCorporate($collection)) {
            return self::clean(self::corporateAffiliation($collection)?->rif) ?? self::holderDocument($collection);
        }

        return self::clean(self::individualAffiliation($collection)?->nro_identificacion_payer) ?? self::holderDocument($collection);
    }

    public static function planLabel(Collection $collection): ?string
    {
        $plan = self::clean($collection->plan?->description);

        if ($plan !== null || ! self::isCorporate($collection)) {
            return $plan;
        }

        $plans = self::corporateAffiliation($collection)?->affiliationCorporatePlans
            ?->map(fn ($corporatePlan): ?string => self::clean($corporatePlan->plan?->description))
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        return $plans === [] ? null : implode(' / ', $plans);
    }

    public static function agencyLabel(Collection $collection): ?string
    {
        $name = self::clean($collection->agencyByCode?->name_corporative);
        $code = self::clean($collection->code_agency);

        return match (true) {
            $name !== null && $code !== null => $code.' · '.$name,
            default => $name ?? $code,
        };
    }

    public static function annualFee(Collection $collection): ?float
    {
        $fee = self::isCorporate($collection)
            ? self::corporateAffiliation($collection)?->fee_anual
            : self::individualAffiliation($collection)?->fee_anual;

        return is_numeric($fee) ? (float) $fee : null;
    }

    public static function effectiveDate(Collection $collection): ?string
    {
        return self::clean(self::isCorporate($collection)
            ? self::corporateAffiliation($collection)?->effective_date
            : self::individualAffiliation($collection)?->effective_date);
    }

    /**
     * Estatus vigente de la afiliación; si ya no existe, el que se copió en la cuota.
     */
    public static function affiliateStatus(Collection $collection): ?string
    {
        $status = self::isCorporate($collection)
            ? self::corporateAffiliation($collection)?->status
            : self::individualAffiliation($collection)?->status;

        $status = self::clean($status) ?? self::clean($collection->affiliate_status);

        return $status === null ? null : Str::upper($status);
    }

    /**
     * Totales del resumen sobre **todas** las cuotas pendientes de las afiliaciones de
     * la consulta (no solo la próxima), calculados en la base sin cargar filas. Recibe
     * la consulta de la tabla para respetar filtros y búsqueda.
     *
     * @return array{rows_count: int, pending_count: int, pending_amount: float, overdue_count: int, overdue_amount: float, due_soon_count: int, due_soon_amount: float, due_windows: array<int, array{count: int, amount: float}>}
     */
    public static function summary(?Builder $query = null, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $todayString = $today->toDateString();
        $soonString = $today->addDays(7)->toDateString();

        $rows = $query !== null
            ? (clone $query)->reorder()
            : self::scopeNextPendingPerAffiliation(Collection::query());

        $rows->setEagerLoads([]);
        $rows->getQuery()->columns = null;
        $rows->getQuery()->limit = null;
        $rows->getQuery()->offset = null;

        $rowsCount = (clone $rows)->count();

        $totalsQuery = Collection::query()
            ->where('status', self::PENDING_STATUS)
            ->whereIn('affiliation_code', (clone $rows)->select($rows->getModel()->getTable().'.affiliation_code'))
            ->selectRaw('COUNT(*) as pending_count, COALESCE(SUM(total_amount), 0) as pending_amount')
            ->selectRaw('SUM(CASE WHEN filter_next_payment_date < ? THEN 1 ELSE 0 END) as overdue_count', [$todayString])
            ->selectRaw('COALESCE(SUM(CASE WHEN filter_next_payment_date < ? THEN total_amount ELSE 0 END), 0) as overdue_amount', [$todayString])
            ->selectRaw('SUM(CASE WHEN filter_next_payment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_soon_count', [$todayString, $soonString])
            ->selectRaw('COALESCE(SUM(CASE WHEN filter_next_payment_date BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as due_soon_amount', [$todayString, $soonString]);

        // Los plazos van en la misma consulta: un solo recorrido para todo el resumen.
        foreach (array_keys(self::DUE_WINDOWS) as $days) {
            $untilString = $today->addDays($days)->toDateString();

            $totalsQuery
                ->selectRaw("SUM(CASE WHEN filter_next_payment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_{$days}_count", [$todayString, $untilString])
                ->selectRaw("COALESCE(SUM(CASE WHEN filter_next_payment_date BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as due_{$days}_amount", [$todayString, $untilString]);
        }

        $totals = $totalsQuery->toBase()->first();

        $dueWindows = [];

        foreach (array_keys(self::DUE_WINDOWS) as $days) {
            $dueWindows[$days] = [
                'count' => (int) ($totals->{"due_{$days}_count"} ?? 0),
                'amount' => (float) ($totals->{"due_{$days}_amount"} ?? 0),
            ];
        }

        return [
            'rows_count' => $rowsCount,
            'pending_count' => (int) ($totals->pending_count ?? 0),
            'pending_amount' => (float) ($totals->pending_amount ?? 0),
            'overdue_count' => (int) ($totals->overdue_count ?? 0),
            'overdue_amount' => (float) ($totals->overdue_amount ?? 0),
            'due_soon_count' => (int) ($totals->due_soon_count ?? 0),
            'due_soon_amount' => (float) ($totals->due_soon_amount ?? 0),
            'due_windows' => $dueWindows,
        ];
    }

    private static function individualAffiliation(Collection $collection): ?Affiliation
    {
        $affiliation = $collection->affiliationByCode;

        return $affiliation instanceof Affiliation ? $affiliation : null;
    }

    private static function corporateAffiliation(Collection $collection): ?AffiliationCorporate
    {
        $affiliation = $collection->affiliationCorporateByCode;

        return $affiliation instanceof AffiliationCorporate ? $affiliation : null;
    }

    private static function clean(mixed $value): ?string
    {
        try {
            $value = trim((string) ($value ?? ''));
        } catch (Throwable) {
            return null;
        }

        return $value === '' || $value === 'N/A' ? null : $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AnnualCollection;
use App\Models\Collection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reporte de cuentas por cobrar (CXC) sobre «Cobranza por mes».
 *
 * Cada fila es un año de contrato de una afiliación (`annual_collections`) y muestra
 * su **próxima cuota pendiente** (`collections` del mismo `sale_id` con estatus
 * POR PAGAR, la de vencimiento más cercano). `annual_collections` no guarda
 * frecuencia, monto ni un estatus fiable (todas dicen POR PAGAR), así que el
 * vencimiento, el estatus VENCIDO, los días y el número de cuota salen de esa cuota
 * y se calculan contra la fecha de hoy.
 */
final class CollectionReceivableReport
{
    public const PENDING_STATUS = 'POR PAGAR';

    public const STATUS_PENDING = 'POR PAGAR';

    public const STATUS_OVERDUE = 'VENCIDO';

    public const AGING_DUE_SOON = 'vence_7';

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
            'pendingCollections:id,sale_id,include_date,payment_frequency,filter_next_payment_date,total_amount,status',
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
     * SQL del vencimiento de la próxima cuota pendiente de la fila, para filtrar y
     * ordenar en la base.
     */
    public static function nextDueSql(): string
    {
        return "(select min(pending.filter_next_payment_date) from collections as pending where pending.sale_id = annual_collections.sale_id and pending.status = '".self::PENDING_STATUS."')";
    }

    public static function scopePending(Builder $query): Builder
    {
        return $query->whereHas('pendingCollections');
    }

    /**
     * @return array<string, string>
     */
    public static function agingOptions(): array
    {
        return [
            self::AGING_DUE_SOON => 'Vence en los próximos 7 días',
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
        $due = self::nextDueSql();

        return match ($bucket) {
            self::AGING_DUE_SOON => $query->whereRaw("{$due} between ? and ?", [$today->toDateString(), $today->addDays(7)->toDateString()]),
            self::AGING_NOT_DUE => $query->whereRaw("{$due} >= ?", [$today->toDateString()]),
            self::AGING_OVERDUE_1_30 => $query->whereRaw("{$due} between ? and ?", [$today->subDays(30)->toDateString(), $today->subDay()->toDateString()]),
            self::AGING_OVERDUE_31_60 => $query->whereRaw("{$due} between ? and ?", [$today->subDays(60)->toDateString(), $today->subDays(31)->toDateString()]),
            self::AGING_OVERDUE_61_90 => $query->whereRaw("{$due} between ? and ?", [$today->subDays(90)->toDateString(), $today->subDays(61)->toDateString()]),
            self::AGING_OVERDUE_90_PLUS => $query->whereRaw("{$due} < ?", [$today->subDays(90)->toDateString()]),
            default => $query,
        };
    }

    public static function applyCollectionStatus(Builder $query, ?string $status, ?CarbonImmutable $today = null): Builder
    {
        $today ??= CarbonImmutable::today();
        $due = self::nextDueSql();

        return match ($status) {
            self::STATUS_OVERDUE => $query->whereRaw("{$due} < ?", [$today->toDateString()]),
            self::STATUS_PENDING => $query->whereRaw("{$due} >= ?", [$today->toDateString()]),
            default => $query,
        };
    }

    public static function applyDueBetween(Builder $query, ?string $from, ?string $until): Builder
    {
        $due = self::nextDueSql();

        return $query
            ->when($from, fn (Builder $query, string $date): Builder => $query->whereRaw("{$due} >= ?", [CarbonImmutable::parse($date)->toDateString()]))
            ->when($until, fn (Builder $query, string $date): Builder => $query->whereRaw("{$due} <= ?", [CarbonImmutable::parse($date)->toDateString()]));
    }

    /**
     * Filtra por el estatus vigente de la afiliación (individual o corporativa). Si la
     * afiliación ya no existe se usa el estatus copiado en la fila.
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

    public static function nextInstallment(AnnualCollection $row): ?Collection
    {
        $installment = $row->pendingCollections->first();

        return $installment instanceof Collection ? $installment : null;
    }

    public static function dueDate(AnnualCollection $row): ?CarbonImmutable
    {
        $value = self::nextInstallment($row)?->filter_next_payment_date;

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Días hasta el vencimiento de la próxima cuota: negativo si ya venció.
     */
    public static function daysUntilDue(AnnualCollection $row, ?CarbonImmutable $today = null): ?int
    {
        $due = self::dueDate($row);

        if ($due === null) {
            return null;
        }

        return (int) ($today ?? CarbonImmutable::today())->diffInDays($due, false);
    }

    public static function collectionStatus(AnnualCollection $row, ?CarbonImmutable $today = null): string
    {
        $days = self::daysUntilDue($row, $today);

        return $days !== null && $days < 0 ? self::STATUS_OVERDUE : self::STATUS_PENDING;
    }

    /**
     * Días en positivo para el reporte: de atraso si está vencida, para vencer si no.
     */
    public static function daysCount(AnnualCollection $row, ?CarbonImmutable $today = null): ?int
    {
        $days = self::daysUntilDue($row, $today);

        return $days === null ? null : abs($days);
    }

    public static function daysLabel(AnnualCollection $row, ?CarbonImmutable $today = null): string
    {
        $days = self::daysUntilDue($row, $today);

        return match (true) {
            $days === null => 'Sin fecha',
            $days < 0 => abs($days).' '.(abs($days) === 1 ? 'día' : 'días').' de atraso',
            $days === 0 => 'Vence hoy',
            default => 'Faltan '.$days.' '.($days === 1 ? 'día' : 'días'),
        };
    }

    public static function paymentFrequency(AnnualCollection $row): ?string
    {
        $frequency = self::isCorporate($row)
            ? self::corporateAffiliation($row)?->payment_frequency
            : self::individualAffiliation($row)?->payment_frequency;

        $frequency = self::clean($frequency) ?? self::clean(self::nextInstallment($row)?->payment_frequency);

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
     * Número de la próxima cuota dentro del año de contrato, p. ej. «2 de 4».
     *
     * Sale de los meses entre el inicio del período (`include_date`) y el vencimiento
     * de la cuota; no depende de que exista la fila de la primera cuota, que en los
     * datos no siempre se creó.
     */
    public static function installmentLabel(AnnualCollection $row): ?string
    {
        $perYear = self::installmentsPerYear(self::paymentFrequency($row));
        $due = self::dueDate($row);
        $start = self::parseDisplayDate(self::nextInstallment($row)?->include_date ?? $row->include_date);

        if ($perYear === null || $due === null || $start === null) {
            return null;
        }

        if ($perYear === 1) {
            return '1 de 1';
        }

        $monthsPerInstallment = intdiv(12, $perYear);
        $elapsedMonths = (int) round($start->diffInMonths($due, false));
        $number = intdiv(max($elapsedMonths, 0), $monthsPerInstallment) + 1;

        return min($number, $perYear).' de '.$perYear;
    }

    public static function installmentAmount(AnnualCollection $row): ?float
    {
        $amount = self::nextInstallment($row)?->total_amount;

        return is_numeric($amount) ? (float) $amount : null;
    }

    public static function isCorporate(AnnualCollection $row): bool
    {
        return str_contains(Str::upper(Str::ascii((string) $row->type)), 'CORPORATIVA');
    }

    public static function holderName(AnnualCollection $row): ?string
    {
        return self::clean($row->affiliate_full_name);
    }

    public static function holderDocument(AnnualCollection $row): ?string
    {
        return self::clean($row->affiliate_ci_rif);
    }

    /**
     * Tomador = quien paga. En individuales es el pagador de la afiliación; en
     * corporativas es la empresa contratante.
     */
    public static function payerName(AnnualCollection $row): ?string
    {
        if (self::isCorporate($row)) {
            return self::clean(self::corporateAffiliation($row)?->name_corporate) ?? self::holderName($row);
        }

        return self::clean(self::individualAffiliation($row)?->full_name_payer) ?? self::holderName($row);
    }

    public static function payerDocument(AnnualCollection $row): ?string
    {
        if (self::isCorporate($row)) {
            return self::clean(self::corporateAffiliation($row)?->rif) ?? self::holderDocument($row);
        }

        return self::clean(self::individualAffiliation($row)?->nro_identificacion_payer) ?? self::holderDocument($row);
    }

    public static function planLabel(AnnualCollection $row): ?string
    {
        $plan = self::clean($row->plan?->description);

        if ($plan !== null || ! self::isCorporate($row)) {
            return $plan;
        }

        $plans = self::corporateAffiliation($row)?->affiliationCorporatePlans
            ?->map(fn ($corporatePlan): ?string => self::clean($corporatePlan->plan?->description))
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        return $plans === [] ? null : implode(' / ', $plans);
    }

    public static function agencyLabel(AnnualCollection $row): ?string
    {
        $name = self::clean($row->agencyByCode?->name_corporative);
        $code = self::clean($row->code_agency);

        return match (true) {
            $name !== null && $code !== null => $code.' · '.$name,
            default => $name ?? $code,
        };
    }

    public static function annualFee(AnnualCollection $row): ?float
    {
        $fee = self::isCorporate($row)
            ? self::corporateAffiliation($row)?->fee_anual
            : self::individualAffiliation($row)?->fee_anual;

        return is_numeric($fee) ? (float) $fee : null;
    }

    public static function effectiveDate(AnnualCollection $row): ?string
    {
        return self::clean(self::isCorporate($row)
            ? self::corporateAffiliation($row)?->effective_date
            : self::individualAffiliation($row)?->effective_date);
    }

    /**
     * Estatus vigente de la afiliación; si ya no existe, el que se copió en la fila.
     */
    public static function affiliateStatus(AnnualCollection $row): ?string
    {
        $status = self::isCorporate($row)
            ? self::corporateAffiliation($row)?->status
            : self::individualAffiliation($row)?->status;

        $status = self::clean($status) ?? self::clean($row->affiliate_status);

        return $status === null ? null : Str::upper($status);
    }

    /**
     * Totales del resumen sobre **todas** las cuotas pendientes de las filas de la
     * consulta (no solo la próxima), calculados en la base sin cargar filas. Recibe
     * la consulta de la tabla para respetar filtros y búsqueda.
     *
     * @return array{rows_count: int, pending_count: int, pending_amount: float, overdue_count: int, overdue_amount: float, due_soon_count: int, due_soon_amount: float}
     */
    public static function summary(?Builder $query = null, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $todayString = $today->toDateString();
        $soonString = $today->addDays(7)->toDateString();

        $rows = $query !== null
            ? (clone $query)->reorder()
            : self::scopePending(AnnualCollection::query());

        $rows->setEagerLoads([]);
        $rows->getQuery()->columns = null;
        $rows->getQuery()->limit = null;
        $rows->getQuery()->offset = null;

        $rowsCount = (clone $rows)->count();

        $totals = Collection::query()
            ->where('status', self::PENDING_STATUS)
            ->whereIn('sale_id', (clone $rows)->select('annual_collections.sale_id'))
            ->selectRaw('COUNT(*) as pending_count, COALESCE(SUM(total_amount), 0) as pending_amount')
            ->selectRaw('SUM(CASE WHEN filter_next_payment_date < ? THEN 1 ELSE 0 END) as overdue_count', [$todayString])
            ->selectRaw('COALESCE(SUM(CASE WHEN filter_next_payment_date < ? THEN total_amount ELSE 0 END), 0) as overdue_amount', [$todayString])
            ->selectRaw('SUM(CASE WHEN filter_next_payment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_soon_count', [$todayString, $soonString])
            ->selectRaw('COALESCE(SUM(CASE WHEN filter_next_payment_date BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as due_soon_amount', [$todayString, $soonString])
            ->toBase()
            ->first();

        return [
            'rows_count' => $rowsCount,
            'pending_count' => (int) ($totals->pending_count ?? 0),
            'pending_amount' => (float) ($totals->pending_amount ?? 0),
            'overdue_count' => (int) ($totals->overdue_count ?? 0),
            'overdue_amount' => (float) ($totals->overdue_amount ?? 0),
            'due_soon_count' => (int) ($totals->due_soon_count ?? 0),
            'due_soon_amount' => (float) ($totals->due_soon_amount ?? 0),
        ];
    }

    private static function individualAffiliation(AnnualCollection $row): ?Affiliation
    {
        $affiliation = $row->affiliationByCode;

        return $affiliation instanceof Affiliation ? $affiliation : null;
    }

    private static function corporateAffiliation(AnnualCollection $row): ?AffiliationCorporate
    {
        $affiliation = $row->affiliationCorporateByCode;

        return $affiliation instanceof AffiliationCorporate ? $affiliation : null;
    }

    private static function parseDisplayDate(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            return preg_match('#^\d{2}/\d{2}/\d{4}$#', $value) === 1
                ? CarbonImmutable::createFromFormat('d/m/Y', $value)->startOfDay()
                : CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private static function clean(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' || $value === 'N/A' ? null : $value;
    }
}

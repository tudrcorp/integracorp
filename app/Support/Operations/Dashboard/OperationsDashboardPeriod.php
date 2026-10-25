<?php

declare(strict_types=1);

namespace App\Support\Operations\Dashboard;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Período del Dashboard de Operaciones y su período de comparación.
 *
 * El período anterior abarca la misma cantidad de días del tramo previo, así la
 * comparación es justa: el mes en curso (1 al 24) se compara con el 1 al 24 del
 * mes anterior, no con el mes completo.
 */
final class OperationsDashboardPeriod
{
    public const THIS_MONTH = 'this_month';

    public const LAST_MONTH = 'last_month';

    public const LAST_7 = 'last_7';

    public const LAST_30 = 'last_30';

    public const LAST_90 = 'last_90';

    public const THIS_QUARTER = 'this_quarter';

    public const THIS_YEAR = 'this_year';

    public const CUSTOM = 'custom';

    public const DEFAULT = self::THIS_MONTH;

    /** Un rango personalizado no puede pasar de esto: protege las consultas. */
    public const MAX_DAYS = 731;

    public const GRANULARITY_DAY = 'day';

    public const GRANULARITY_WEEK = 'week';

    public const GRANULARITY_MONTH = 'month';

    private const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    private function __construct(
        public readonly string $preset,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly CarbonImmutable $previousFrom,
        public readonly CarbonImmutable $previousTo,
        public readonly bool $compare,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::THIS_MONTH => 'Mes en curso',
            self::LAST_MONTH => 'Mes anterior',
            self::LAST_7 => 'Últimos 7 días',
            self::LAST_30 => 'Últimos 30 días',
            self::LAST_90 => 'Últimos 90 días',
            self::THIS_QUARTER => 'Trimestre en curso',
            self::THIS_YEAR => 'Año en curso',
            self::CUSTOM => 'Rango personalizado',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters, ?CarbonImmutable $today = null): self
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $preset = (string) ($filters['period'] ?? self::DEFAULT);
        $compare = filter_var($filters['compare'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (! array_key_exists($preset, self::options())) {
            $preset = self::DEFAULT;
        }

        [$from, $to] = match ($preset) {
            self::LAST_MONTH => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            self::LAST_7 => [$today->subDays(6), $today],
            self::LAST_30 => [$today->subDays(29), $today],
            self::LAST_90 => [$today->subDays(89), $today],
            self::THIS_QUARTER => [$today->startOfQuarter(), $today],
            self::THIS_YEAR => [$today->startOfYear(), $today],
            self::CUSTOM => self::customRange($filters, $today),
            default => [$today->startOfMonth(), $today],
        };

        [$previousFrom, $previousTo] = self::previousRange($preset, $from, $to);

        return new self($preset, $from, $to, $previousFrom, $previousTo, $compare);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function granularity(): string
    {
        return match (true) {
            $this->days() <= 45 => self::GRANULARITY_DAY,
            $this->days() <= 200 => self::GRANULARITY_WEEK,
            default => self::GRANULARITY_MONTH,
        };
    }

    public function label(): string
    {
        return self::rangeLabel($this->from, $this->to);
    }

    public function previousLabel(): string
    {
        return self::rangeLabel($this->previousFrom, $this->previousTo);
    }

    /**
     * Clave estable para la caché.
     */
    public function cacheKey(): string
    {
        return $this->from->toDateString().'_'.$this->to->toDateString().'_'.$this->previousFrom->toDateString().'_'.$this->previousTo->toDateString();
    }

    public static function rangeLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->day.' '.self::MONTHS[$from->month - 1].' '.$from->year;
        }

        if ($from->isSameMonth($to)) {
            return $from->day.'–'.$to->day.' '.self::MONTHS[$to->month - 1].' '.$to->year;
        }

        if ($from->isSameYear($to)) {
            return $from->day.' '.self::MONTHS[$from->month - 1].' – '.$to->day.' '.self::MONTHS[$to->month - 1].' '.$to->year;
        }

        return $from->day.' '.self::MONTHS[$from->month - 1].' '.$from->year.' – '.$to->day.' '.self::MONTHS[$to->month - 1].' '.$to->year;
    }

    public static function monthShort(int $month): string
    {
        return self::MONTHS[max(1, min(12, $month)) - 1];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function customRange(?array $filters, CarbonImmutable $today): array
    {
        $from = self::parseDate($filters['from'] ?? null) ?? $today->startOfMonth();
        $to = self::parseDate($filters['to'] ?? null) ?? $today;

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function previousRange(string $preset, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $elapsed = (int) $from->diffInDays($to);

        return match ($preset) {
            /** Mismo tramo del mes anterior, recortado si ese mes es más corto. */
            self::THIS_MONTH => [
                $from->subMonthNoOverflow()->startOfMonth(),
                self::minDate($from->subMonthNoOverflow()->startOfMonth()->addDays($elapsed), $from->subMonthNoOverflow()->endOfMonth()->startOfDay()),
            ],
            self::LAST_MONTH => [$from->subMonthNoOverflow()->startOfMonth(), $from->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            self::THIS_QUARTER => [
                $from->subQuarterNoOverflow()->startOfQuarter(),
                self::minDate($from->subQuarterNoOverflow()->startOfQuarter()->addDays($elapsed), $from->subQuarterNoOverflow()->endOfQuarter()->startOfDay()),
            ],
            self::THIS_YEAR => [$from->subYear()->startOfYear(), $from->subYear()->startOfYear()->addDays($elapsed)],
            default => [$from->subDays($elapsed + 1), $from->subDay()],
        };
    }

    private static function minDate(CarbonImmutable $a, CarbonImmutable $b): CarbonImmutable
    {
        return $a->lessThan($b) ? $a : $b;
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}

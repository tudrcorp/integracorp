<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Models\OperationServiceStatistic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Periodo y filtros opcionales de un reporte de Operaciones.
 *
 * El periodo se aplica sobre `started_on` (fecha de inicio del servicio), que
 * está indexada. Todo lo que llega del formulario se normaliza aquí: el job en
 * cola vuelve a construir el objeto con `fromArray()`, así que nunca confía en
 * un estado ya armado.
 */
final readonly class OperationReportFilters
{
    public const PERIOD_TODAY = 'today';

    public const PERIOD_THIS_WEEK = 'this_week';

    public const PERIOD_THIS_MONTH = 'this_month';

    public const PERIOD_LAST_MONTH = 'last_month';

    public const PERIOD_CUSTOM = 'custom';

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $coverages
     * @param  list<string>  $businessLines
     * @param  list<string>  $services
     * @param  list<string>  $serviceProviders
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $statuses = [],
        public array $coverages = [],
        public array $businessLines = [],
        public array $services = [],
        public array $serviceProviders = [],
    ) {
        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            self::PERIOD_TODAY => 'Hoy',
            self::PERIOD_THIS_WEEK => 'Esta semana',
            self::PERIOD_THIS_MONTH => 'Este mes',
            self::PERIOD_LAST_MONTH => 'Mes anterior',
            self::PERIOD_CUSTOM => 'Personalizado',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        [$from, $to] = self::resolvePeriod(
            (string) ($data['period'] ?? self::PERIOD_THIS_MONTH),
            $data['from'] ?? null,
            $data['to'] ?? null,
            $now,
        );

        return new self(
            from: $from,
            to: $to,
            statuses: self::strings($data['statuses'] ?? []),
            coverages: self::strings($data['coverages'] ?? []),
            businessLines: self::strings($data['business_lines'] ?? []),
            services: self::strings($data['services'] ?? []),
            serviceProviders: self::strings($data['service_providers'] ?? []),
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function resolvePeriod(string $period, mixed $from, mixed $to, CarbonImmutable $now): array
    {
        return match ($period) {
            self::PERIOD_TODAY => [$now->startOfDay(), $now->endOfDay()],
            self::PERIOD_THIS_WEEK => [$now->startOfWeek(), $now->endOfDay()],
            self::PERIOD_LAST_MONTH => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            self::PERIOD_CUSTOM => [self::date($from, 'inicial')->startOfDay(), self::date($to, 'final')->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfDay()],
        };
    }

    /**
     * @param  Builder<OperationServiceStatistic>  $query
     * @return Builder<OperationServiceStatistic>
     */
    public function apply(Builder $query): Builder
    {
        $query->whereBetween('started_on', [$this->from->toDateString(), $this->to->toDateString()]);

        if ($this->statuses !== []) {
            $query->whereIn('service_status', $this->statuses);
        }

        if ($this->coverages !== []) {
            $query->whereIn('coverage', $this->coverages);
        }

        if ($this->businessLines !== []) {
            $query->whereIn('business_line', $this->businessLines);
        }

        if ($this->services !== []) {
            $query->whereIn('service', $this->services);
        }

        if ($this->serviceProviders !== []) {
            $query->whereIn('service_provider', $this->serviceProviders);
        }

        return $query;
    }

    /**
     * Texto legible del periodo y los filtros, para el encabezado del PDF.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = ['Periodo: '.$this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y')];

        foreach ([
            'Estatus' => $this->statuses,
            'Cobertura' => $this->coverages,
            'Línea de negocio' => $this->businessLines,
            'Servicio' => $this->services,
            'Proveedor' => $this->serviceProviders,
        ] as $label => $values) {
            if ($values !== []) {
                $lines[] = $label.': '.implode(', ', $values);
            }
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => self::PERIOD_CUSTOM,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'statuses' => $this->statuses,
            'coverages' => $this->coverages,
            'business_lines' => $this->businessLines,
            'services' => $this->services,
            'service_providers' => $this->serviceProviders,
        ];
    }

    private static function date(mixed $value, string $label): CarbonImmutable
    {
        if (blank($value)) {
            throw new InvalidArgumentException("Indique la fecha {$label} del periodo.");
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            throw new InvalidArgumentException("La fecha {$label} del periodo no es válida.");
        }
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', (array) $values),
            static fn (string $value): bool => $value !== '',
        )));
    }
}

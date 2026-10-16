<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Enums\AdministrationReportType;
use App\Support\Collections\CollectionReceivableReport;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Periodo, estatus y alcance comercial de un reporte de cobranza.
 *
 * El periodo se aplica sobre el vencimiento de la próxima cuota pendiente
 * (`collections.filter_next_payment_date`), igual que el filtro «Fecha de
 * vencimiento» de Cobranza Por Mes. Todo lo que llega del formulario se valida
 * aquí: el job en cola reconstruye el objeto con `fromArray()` y nunca confía
 * en un estado ya armado.
 */
final readonly class CollectionReportFilters
{
    public const PERIOD_THIS_MONTH = 'this_month';

    public const PERIOD_NEXT_MONTH = 'next_month';

    public const PERIOD_LAST_MONTH = 'last_month';

    public const PERIOD_ALL = 'all';

    public const PERIOD_CUSTOM = 'custom';

    public const STATUS_ALL = 'all';

    public const SCOPE_AGENCY = 'agency';

    public const SCOPE_AGENT = 'agent';

    public function __construct(
        public AdministrationReportType $type,
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public ?string $collectionStatus = null,
        public ?string $scope = null,
        public ?string $agencyCode = null,
        public ?int $agentId = null,
        public bool $includeSubagents = true,
    ) {
        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            throw new InvalidArgumentException('La fecha «Desde» no puede ser posterior a la fecha «Hasta».');
        }

        if ($type !== AdministrationReportType::CollectionsByStructure) {
            return;
        }

        if ($scope === self::SCOPE_AGENCY && ($agencyCode === null || $agencyCode === '')) {
            throw new InvalidArgumentException('Seleccione la agencia del reporte.');
        }

        if ($scope === self::SCOPE_AGENT && ($agentId === null || $agentId <= 0)) {
            throw new InvalidArgumentException('Seleccione el agente del reporte.');
        }

        if (! in_array($scope, [self::SCOPE_AGENCY, self::SCOPE_AGENT], true)) {
            throw new InvalidArgumentException('Indique si el reporte es por agencia o por agente.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            self::PERIOD_THIS_MONTH => 'Este mes',
            self::PERIOD_NEXT_MONTH => 'Próximo mes',
            self::PERIOD_LAST_MONTH => 'Mes anterior',
            self::PERIOD_ALL => 'Toda la cartera',
            self::PERIOD_CUSTOM => 'Desde / hasta',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function collectionStatusOptions(): array
    {
        return [
            self::STATUS_ALL => 'Todas',
            CollectionReceivableReport::STATUS_PENDING => 'Por pagar',
            CollectionReceivableReport::STATUS_OVERDUE => 'Vencidas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_AGENCY => 'Por agencia',
            self::SCOPE_AGENT => 'Por agente',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?CarbonImmutable $now = null): self
    {
        $type = AdministrationReportType::tryFrom((string) ($data['type'] ?? ''))
            ?? throw new InvalidArgumentException('Seleccione el reporte que necesita.');

        [$from, $to] = self::resolvePeriod(
            (string) ($data['period'] ?? self::PERIOD_THIS_MONTH),
            $data['from'] ?? null,
            $data['to'] ?? null,
            $now ?? CarbonImmutable::now(),
        );

        $status = trim((string) ($data['collection_status'] ?? ''));
        $isStructure = $type === AdministrationReportType::CollectionsByStructure;
        $scope = $isStructure ? trim((string) ($data['scope'] ?? '')) : '';
        $agencyCode = trim((string) ($data['agency_code'] ?? ''));
        $agentId = is_numeric($data['agent_id'] ?? null) ? (int) $data['agent_id'] : null;

        return new self(
            type: $type,
            from: $from,
            to: $to,
            collectionStatus: array_key_exists($status, self::collectionStatusOptions()) && $status !== self::STATUS_ALL ? $status : null,
            scope: $scope === '' ? null : $scope,
            agencyCode: $isStructure && $scope === self::SCOPE_AGENCY && $agencyCode !== '' ? $agencyCode : null,
            agentId: $isStructure && $scope === self::SCOPE_AGENT ? $agentId : null,
            includeSubagents: (bool) ($data['include_subagents'] ?? true),
        );
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public static function resolvePeriod(string $period, mixed $from, mixed $to, CarbonImmutable $now): array
    {
        return match ($period) {
            self::PERIOD_ALL => [null, null],
            self::PERIOD_NEXT_MONTH => [$now->addMonthNoOverflow()->startOfMonth(), $now->addMonthNoOverflow()->endOfMonth()],
            self::PERIOD_LAST_MONTH => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            self::PERIOD_CUSTOM => [self::date($from, '«Desde»')->startOfDay(), self::date($to, '«Hasta»')->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    /**
     * Texto legible del periodo y los filtros, para el encabezado del PDF y del Excel.
     *
     * @return list<string>
     */
    public function describe(?string $scopeLabel = null): array
    {
        $lines = [
            $this->from === null
                ? 'Vencimiento: toda la cartera por cobrar'
                : 'Vencimiento: del '.$this->from->format('d/m/Y').' al '.$this->to?->format('d/m/Y'),
            'Estatus de cobro: '.self::collectionStatusOptions()[$this->collectionStatus ?? self::STATUS_ALL],
        ];

        if ($scopeLabel !== null && $scopeLabel !== '') {
            $lines[] = $scopeLabel;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'period' => $this->from === null ? self::PERIOD_ALL : self::PERIOD_CUSTOM,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'collection_status' => $this->collectionStatus ?? self::STATUS_ALL,
            'scope' => $this->scope,
            'agency_code' => $this->agencyCode,
            'agent_id' => $this->agentId,
            'include_subagents' => $this->includeSubagents,
        ];
    }

    private static function date(mixed $value, string $label): CarbonImmutable
    {
        if (blank($value)) {
            throw new InvalidArgumentException("Indique la fecha {$label} del periodo.");
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            throw new InvalidArgumentException("La fecha {$label} del periodo no es válida.");
        }
    }
}

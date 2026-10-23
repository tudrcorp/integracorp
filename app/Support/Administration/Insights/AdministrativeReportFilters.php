<?php

declare(strict_types=1);

namespace App\Support\Administration\Insights;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\WhiteCompany;
use App\Support\Administration\Reports\CollectionReportBuilder;
use App\Support\Administration\Reports\CollectionReportNetwork;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use Throwable;

/**
 * Periodo y alcance comercial del Informe administrativo.
 *
 * El alcance usa la misma red que el Centro de reportes: una agencia trae toda su
 * estructura, un agente puede traer a sus subagentes y una empresa aliada trae su
 * raíz y toda su descendencia. Todo lo que llega del formulario se valida aquí: el
 * job del PDF reconstruye el objeto con `fromArray()`.
 */
final readonly class AdministrativeReportFilters
{
    public const PERIOD_THIS_MONTH = 'this_month';

    public const PERIOD_LAST_MONTH = 'last_month';

    public const PERIOD_THIS_YEAR = 'this_year';

    public const PERIOD_LAST_YEAR = 'last_year';

    public const PERIOD_LAST_12_MONTHS = 'last_12_months';

    public const PERIOD_CUSTOM = 'custom';

    public const SCOPE_ALL = 'all';

    public const SCOPE_AGENCY = 'agency';

    public const SCOPE_AGENT = 'agent';

    public const SCOPE_WHITE_COMPANY = 'white_company';

    public const SECTION_SUMMARY = 'summary';

    public const SECTION_SALES = 'sales';

    public const SECTION_RECEIVABLES = 'receivables';

    public const SECTION_COMMISSIONS = 'commissions';

    public const SECTION_RENOVATIONS = 'renovations';

    public const SECTION_DROPOUTS = 'dropouts';

    public const SECTION_WHITE_COMPANIES = 'white_companies';

    public const SECTION_TDEV = 'tdev';

    /** Un informe de más de tres años deja de leerse por mes. */
    public const MAX_MONTHS = 36;

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $scope = self::SCOPE_ALL,
        public ?string $agencyCode = null,
        public ?int $agentId = null,
        public bool $includeSubagents = true,
        public ?int $whiteCompanyId = null,
        /** @var list<string> Vacío = todas. */
        public array $sections = [],
    ) {
        if ($sections !== [] && array_diff($sections, array_keys(self::sectionOptions())) !== []) {
            throw new InvalidArgumentException('Alguna de las secciones elegidas no existe.');
        }

        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('La fecha «Desde» no puede ser posterior a la fecha «Hasta».');
        }

        if ($from->startOfMonth()->diffInMonths($to->startOfMonth()) + 1 > self::MAX_MONTHS) {
            throw new InvalidArgumentException('El periodo puede abarcar como máximo '.self::MAX_MONTHS.' meses.');
        }

        match ($scope) {
            self::SCOPE_ALL => null,
            self::SCOPE_AGENCY => $agencyCode === null || $agencyCode === '' ? throw new InvalidArgumentException('Seleccione la agencia del informe.') : null,
            self::SCOPE_AGENT => $agentId === null || $agentId <= 0 ? throw new InvalidArgumentException('Seleccione el agente del informe.') : null,
            self::SCOPE_WHITE_COMPANY => $whiteCompanyId === null || $whiteCompanyId <= 0 ? throw new InvalidArgumentException('Seleccione la empresa aliada del informe.') : null,
            default => throw new InvalidArgumentException('Indique de quién es el informe.'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            self::PERIOD_THIS_MONTH => 'Este mes',
            self::PERIOD_LAST_MONTH => 'Mes anterior',
            self::PERIOD_THIS_YEAR => 'Este año',
            self::PERIOD_LAST_YEAR => 'Año anterior',
            self::PERIOD_LAST_12_MONTHS => 'Últimos 12 meses',
            self::PERIOD_CUSTOM => 'Desde / hasta',
        ];
    }

    /**
     * Secciones del informe, en el orden en que salen en el PDF.
     *
     * @return array<string, string>
     */
    public static function sectionOptions(): array
    {
        return [
            self::SECTION_SUMMARY => 'Resumen general',
            self::SECTION_SALES => 'Ventas',
            self::SECTION_RECEIVABLES => 'Cobranza',
            self::SECTION_COMMISSIONS => 'Comisiones',
            self::SECTION_RENOVATIONS => 'Renovaciones',
            self::SECTION_DROPOUTS => 'Bajas',
            self::SECTION_WHITE_COMPANIES => 'Empresas aliadas',
            self::SECTION_TDEV => 'TDEV (viajes)',
        ];
    }

    /**
     * Las secciones elegidas en el orden del informe; sin elección, todas.
     *
     * @return list<string>
     */
    public function orderedSections(): array
    {
        $all = array_keys(self::sectionOptions());

        return $this->sections === [] ? $all : array_values(array_intersect($all, $this->sections));
    }

    /**
     * @return array<string, string>
     */
    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_ALL => 'Toda la empresa',
            self::SCOPE_AGENCY => 'Una agencia',
            self::SCOPE_AGENT => 'Un agente',
            self::SCOPE_WHITE_COMPANY => 'Una empresa aliada',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?CarbonImmutable $now = null): self
    {
        [$from, $to] = self::resolvePeriod(
            (string) ($data['period'] ?? self::PERIOD_THIS_YEAR),
            $data['from'] ?? null,
            $data['to'] ?? null,
            $now ?? CarbonImmutable::now(),
        );

        $scope = (string) ($data['scope'] ?? self::SCOPE_ALL);
        $sections = array_values(array_filter(array_map('strval', (array) ($data['sections'] ?? []))));

        if (array_key_exists('sections', $data) && $sections === []) {
            throw new InvalidArgumentException('Elija al menos una sección del informe.');
        }

        $agencyCode = trim((string) ($data['agency_code'] ?? ''));

        return new self(
            from: $from,
            to: $to,
            scope: $scope,
            agencyCode: $scope === self::SCOPE_AGENCY && $agencyCode !== '' ? $agencyCode : null,
            agentId: $scope === self::SCOPE_AGENT && is_numeric($data['agent_id'] ?? null) ? (int) $data['agent_id'] : null,
            includeSubagents: (bool) ($data['include_subagents'] ?? true),
            whiteCompanyId: $scope === self::SCOPE_WHITE_COMPANY && is_numeric($data['white_company_id'] ?? null) ? (int) $data['white_company_id'] : null,
            sections: $sections,
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function resolvePeriod(string $period, mixed $from, mixed $to, CarbonImmutable $now): array
    {
        return match ($period) {
            self::PERIOD_LAST_MONTH => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            self::PERIOD_THIS_MONTH => [$now->startOfMonth(), $now->endOfMonth()],
            self::PERIOD_LAST_YEAR => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
            self::PERIOD_LAST_12_MONTHS => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now->endOfMonth()],
            self::PERIOD_CUSTOM => [self::date($from, '«Desde»')->startOfDay(), self::date($to, '«Hasta»')->endOfDay()],
            default => [$now->startOfYear(), $now->endOfYear()],
        };
    }

    /**
     * El periodo inmediatamente anterior, de la misma duración en meses, para comparar.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previousPeriod(): array
    {
        $months = $this->from->startOfMonth()->diffInMonths($this->to->startOfMonth()) + 1;
        $start = $this->from->startOfMonth()->subMonthsNoOverflow((int) $months);

        return [$start, $this->from->startOfMonth()->subDay()->endOfDay()];
    }

    /**
     * Red comercial del alcance, o null si el informe es de toda la empresa.
     */
    public function network(): ?CollectionReportNetwork
    {
        return match ($this->scope) {
            self::SCOPE_AGENCY => CollectionReportNetwork::forAgency((string) $this->agencyCode),
            self::SCOPE_AGENT => CollectionReportNetwork::forAgent((int) $this->agentId, $this->includeSubagents),
            self::SCOPE_WHITE_COMPANY => new CollectionReportNetwork(WhiteCompanyOwnership::agencyCodesFor((int) $this->whiteCompanyId), []),
            default => null,
        };
    }

    /**
     * Acota una consulta de una tabla con `code_agency`, `owner_code` y `agent_id`
     * (ventas, cuotas, afiliaciones) al alcance del informe.
     */
    public static function applyNetwork(EloquentBuilder|QueryBuilder $query, string $table, ?CollectionReportNetwork $network, bool $hasOwnerCode = true): EloquentBuilder|QueryBuilder
    {
        if ($network === null) {
            return $query;
        }

        if ($network->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (EloquentBuilder|QueryBuilder $scoped) use ($network, $table, $hasOwnerCode): void {
            if ($network->agencyCodes !== []) {
                $scoped->whereIn("{$table}.code_agency", $network->agencyCodes);

                if ($hasOwnerCode) {
                    $scoped->orWhereIn("{$table}.owner_code", $network->agencyCodes);
                }
            }

            if ($network->agentIds !== []) {
                $scoped->orWhereIn("{$table}.agent_id", $network->agentIds);
            }
        });
    }

    public function periodLabel(): string
    {
        return 'Del '.$this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y');
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            self::SCOPE_AGENCY => 'Agencia '.self::agencyName((string) $this->agencyCode).' y toda su estructura',
            self::SCOPE_AGENT => 'Agente '.self::agentName((int) $this->agentId).($this->includeSubagents ? ' y sus subagentes' : ''),
            self::SCOPE_WHITE_COMPANY => 'Empresa aliada '.(WhiteCompany::query()->whereKey($this->whiteCompanyId)->value('name') ?? '#'.$this->whiteCompanyId),
            default => 'Toda la empresa',
        };
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
            'scope' => $this->scope,
            'agency_code' => $this->agencyCode,
            'agent_id' => $this->agentId,
            'include_subagents' => $this->includeSubagents,
            'white_company_id' => $this->whiteCompanyId,
            'sections' => $this->orderedSections(),
        ];
    }

    private static function agencyName(string $code): string
    {
        $name = trim((string) Agency::query()->where('code', $code)->value('name_corporative'));

        return $name === '' ? $code : $code.' · '.$name;
    }

    private static function agentName(int $agentId): string
    {
        $agent = Agent::query()->find($agentId, ['id', 'name', 'code_agent']);

        return $agent instanceof Agent ? CollectionReportBuilder::agentLabel($agent) : '#'.$agentId;
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

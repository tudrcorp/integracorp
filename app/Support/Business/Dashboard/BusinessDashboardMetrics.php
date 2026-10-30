<?php

declare(strict_types=1);

namespace App\Support\Business\Dashboard;

use App\Models\User;
use App\Support\Metrics\Corporate\CorporateCommercialMetrics;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateMapMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cifras del Dashboard de Negocios. Lo que ya calcula el panel corporativo se
 * reutiliza tal cual: Negocios y Métricas deben dar el mismo número. Aquí solo
 * vive lo propio de Negocios: el mapa y la producción del equipo.
 *
 * - Equipo: usuarios activos del departamento NEGOCIOS. Cotizaciones y
 *   afiliaciones guardan al autor por nombre (`created_by`), así que se cruzan
 *   por nombre, sin distinguir mayúsculas ni tildes. El ranking ordena por
 *   afiliaciones nuevas creadas: una renovación no crea afiliación, así que no
 *   suma. Lo que crean los agentes queda fuera.
 * - Mapa: las cotizaciones no guardan estado, por eso no tienen capa.
 */
final class BusinessDashboardMetrics
{
    /** Tablas de producción: tabla => [campo del resumen, ¿tiene `deleted_at`?]. */
    private const PRODUCTION_SOURCES = [
        'individual_quotes' => ['quotes', false],
        'corporate_quotes' => ['quotes', false],
        'affiliations' => ['affiliations', true],
        'affiliation_corporates' => ['affiliations', true],
    ];

    /**
     * @return array{finance: array<string, mixed>, portfolio: array<string, mixed>, commercial: array<string, mixed>}
     */
    public static function kpis(Period $period): array
    {
        return [
            'finance' => CorporateFinanceMetrics::kpis($period),
            'portfolio' => CorporatePortfolioMetrics::kpis($period),
            'commercial' => CorporateCommercialMetrics::kpis($period),
        ];
    }

    /**
     * @return array{rows: list<array{label: string, value: int, key: string, meta: string}>, others: int, total: int}
     */
    public static function teamRanking(Period $period, int $limit = 8): array
    {
        return CorporateMetrics::remember('business_team_'.$limit, $period, static function () use ($period, $limit): array {
            $team = self::teamNames();
            $people = [];

            foreach (self::PRODUCTION_SOURCES as $table => [$field, $softDeletes]) {
                $rows = DB::table($table)
                    ->whereBetween("{$table}.created_at", CorporateMetrics::range($period->from, $period->to))
                    ->when($softDeletes, static fn ($query) => $query->whereNull("{$table}.deleted_at"))
                    ->selectRaw('created_by, COUNT(*) as total')
                    ->groupBy('created_by')
                    ->pluck('total', 'created_by');

                foreach ($rows as $name => $total) {
                    $key = self::normalizeName((string) $name);

                    if (! isset($team[$key])) {
                        continue;
                    }

                    $people[$key] ??= ['label' => $team[$key], 'quotes' => 0, 'affiliations' => 0];
                    $people[$key][$field] += (int) $total;
                }
            }

            foreach (BusinessNewVsRenewalMetrics::newIncomeByCreator($period->from, $period->to) as $name => $amount) {
                $key = self::normalizeName((string) $name);

                if (isset($team[$key])) {
                    $people[$key] ??= ['label' => $team[$key], 'quotes' => 0, 'affiliations' => 0];
                    $people[$key]['new_income'] = ($people[$key]['new_income'] ?? 0) + $amount;
                }
            }

            $mapped = collect($people)
                ->map(static fn (array $person, string $key): array => [
                    'label' => $person['label'],
                    'value' => $person['affiliations'],
                    'key' => $key,
                    'meta' => $person['quotes'].' cot. · US$ '.number_format((float) ($person['new_income'] ?? 0), 0, ',', '.').' nuevos',
                ])
                ->sortBy([['value', 'desc'], ['label', 'asc']])
                ->values();

            return [
                'rows' => $mapped->take($limit)->all(),
                'others' => (int) $mapped->slice($limit)->sum('value'),
                'total' => (int) $mapped->sum('value'),
            ];
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function mapLayers(Period $period): array
    {
        return CorporateMetrics::remember('business_map_layers', $period, static function () use ($period): array {
            $layers = CorporateMapMetrics::layers($period);

            return [
                'income' => $layers['income'],
                'new_affiliations' => CorporateMapMetrics::layer(
                    'Afiliaciones nuevas',
                    'Nuevas',
                    false,
                    false,
                    'Afiliaciones individuales y corporativas emitidas en el período.',
                    CorporatePortfolioMetrics::newByState($period->from, $period->to),
                    $period->compare ? CorporatePortfolioMetrics::newByState($period->previousFrom, $period->previousTo) : null,
                ),
                'affiliations' => $layers['affiliations'],
                'network' => $layers['network'],
            ];
        });
    }

    /**
     * @return array<string, string> Nombre normalizado => nombre a mostrar.
     */
    private static function teamNames(): array
    {
        return User::query()
            ->where('status', 'ACTIVO')
            ->where('departament', 'like', '%"NEGOCIOS"%')
            ->pluck('name')
            ->mapWithKeys(static fn (mixed $name): array => [self::normalizeName((string) $name) => trim((string) $name)])
            ->all();
    }

    private static function normalizeName(string $name): string
    {
        return Str::upper(Str::ascii(preg_replace('/\s+/', ' ', trim($name)) ?? ''));
    }
}

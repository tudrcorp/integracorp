<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Models\QuoteDeletionLog;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Efectividad de la fuerza de ventas: cuánto cotiza cada agente (o agencia) y cuánto de eso se
 * convierte en afiliación.
 *
 * Reglas (verificadas contra los datos el 09/11/2026):
 * - **A quién se atribuye**: al agente y a la agencia **de la cotización** (quien cotizó), no a
 *   los de la afiliación: en 86 de 356 afiliaciones el agente cambió al afiliar.
 * - **Cotizaciones**: individuales + corporativas creadas en el período, más las borradas que la
 *   bitácora registra (desde el 09/11/2026). Las que no tienen agente cuentan solo para su agencia.
 * - **Convertida**: tiene una afiliación vigente (no eliminada) creada desde ella, de cualquier
 *   plan (individual: `affiliations.individual_quote_id`; corporativa:
 *   `affiliation_corporates.corporate_quote_id`).
 * - **En curso**: ni convertida ni anulada ni borrada: todavía puede convertirse.
 * - **Efectividad**: convertidas ÷ cotizaciones. Para ordenar «mejor efectividad» se usa el
 *   límite inferior del intervalo de Wilson al 95 %: premia la tasa con respaldo de volumen
 *   (10 de 30 —piso 19,2 %— queda por encima de 2 de 5 —piso 11,8 %—). Solo entran quienes tienen al menos
 *   {@see self::MIN_QUOTES} cotizaciones en el período.
 */
final class CorporateQuoteEffectiveness
{
    public const AGENT = 'agent';

    public const AGENCY = 'agency';

    public const MIN_QUOTES = 5;

    public const TOP = 10;

    public const HEAD_OFFICE_CODE = 'TDG-100';

    private const Z = 1.96;

    /**
     * @return array{
     *     rows: list<array{key: string, label: string, sublabel: ?string, created: int, issued: int, anulated: int, deleted: int, open: int, rate: ?float, score: float, individual_created: int, individual_issued: int, corporate_created: int, corporate_issued: int, previous_created: int, previous_issued: int, previous_rate: ?float, rate_delta: ?float}>,
     *     network: array{created: int, issued: int, rate: ?float, previous_rate: ?float, sellers: int, eligible: int},
     *     without_agent: int
     * }
     */
    public static function build(Period $period, string $dimension): array
    {
        return CorporateMetrics::remember('commercial_quote_effectiveness_'.$dimension, $period, static function () use ($period, $dimension): array {
            $current = self::aggregate($dimension, $period->from, $period->to);
            $previous = self::aggregate($dimension, $period->previousFrom, $period->previousTo);
            $labels = self::labels($dimension, array_keys($current));

            $rows = [];

            foreach ($current as $key => $data) {
                $prev = $previous[$key] ?? null;
                $rate = self::rate($data['issued'], $data['created']);
                $previousRate = $prev !== null ? self::rate($prev['issued'], $prev['created']) : null;

                $rows[] = [
                    'key' => (string) $key,
                    'label' => $labels[$key]['label'] ?? (string) $key,
                    'sublabel' => $labels[$key]['sublabel'] ?? null,
                    ...$data,
                    'open' => max(0, $data['created'] - $data['issued'] - $data['anulated'] - $data['deleted']),
                    'rate' => $rate,
                    'score' => self::wilsonLowerBound($data['issued'], $data['created']),
                    'previous_created' => $prev['created'] ?? 0,
                    'previous_issued' => $prev['issued'] ?? 0,
                    'previous_rate' => $previousRate,
                    'rate_delta' => $rate !== null && $previousRate !== null ? round($rate - $previousRate, 1) : null,
                ];
            }

            $created = array_sum(array_column($rows, 'created'));
            $issued = array_sum(array_column($rows, 'issued'));
            $previousCreated = array_sum(array_map(fn (array $item): int => $item['created'], $previous));
            $previousIssued = array_sum(array_map(fn (array $item): int => $item['issued'], $previous));

            return [
                'rows' => $rows,
                'network' => [
                    'created' => $created,
                    'issued' => $issued,
                    'rate' => self::rate($issued, $created),
                    'previous_rate' => self::rate($previousIssued, $previousCreated),
                    'sellers' => count($rows),
                    'eligible' => count(array_filter($rows, fn (array $row): bool => $row['created'] >= self::MIN_QUOTES)),
                ],
                'without_agent' => $dimension === self::AGENT ? self::withoutAgent($period->from, $period->to) : 0,
            ];
        });
    }

    /**
     * Las tres vistas del top: más cotizan, más convierten y mejor efectividad.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{volume: list<array<string, mixed>>, conversions: list<array<string, mixed>>, effectiveness: list<array<string, mixed>>}
     */
    public static function rankings(array $rows): array
    {
        $volume = $rows;
        usort($volume, fn (array $a, array $b): int => [$b['created'], $b['issued'], $a['label']] <=> [$a['created'], $a['issued'], $b['label']]);

        $conversions = array_values(array_filter($rows, fn (array $row): bool => $row['issued'] > 0));
        usort($conversions, fn (array $a, array $b): int => [$b['issued'], $b['rate'] ?? 0, $a['label']] <=> [$a['issued'], $a['rate'] ?? 0, $b['label']]);

        $effectiveness = array_values(array_filter($rows, fn (array $row): bool => $row['created'] >= self::MIN_QUOTES));
        usort($effectiveness, fn (array $a, array $b): int => [$b['score'], $b['issued'], $a['label']] <=> [$a['score'], $a['issued'], $b['label']]);

        return [
            'volume' => array_slice($volume, 0, self::TOP),
            'conversions' => array_slice($conversions, 0, self::TOP),
            'effectiveness' => array_slice($effectiveness, 0, self::TOP),
        ];
    }

    /**
     * Límite inferior del intervalo de Wilson al 95 % (en %): una tasa con poco volumen queda
     * cerca de cero; con más cotizaciones, se acerca a la tasa observada.
     */
    public static function wilsonLowerBound(int $successes, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        $p = $successes / $total;
        $z2 = self::Z ** 2;
        $center = $p + $z2 / (2 * $total);
        $margin = self::Z * sqrt(($p * (1 - $p) + $z2 / (4 * $total)) / $total);

        return round(max(0.0, ($center - $margin) / (1 + $z2 / $total)) * 100, 2);
    }

    public static function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round($part * 100 / $total, 1) : null;
    }

    /**
     * @return array<string, array{created: int, issued: int, anulated: int, deleted: int, individual_created: int, individual_issued: int, corporate_created: int, corporate_issued: int}>
     */
    private static function aggregate(string $dimension, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $column = $dimension === self::AGENT ? 'agent_id' : 'code_agency';
        $range = CorporateMetrics::range($from, $to);
        $out = [];

        $add = function (string $key, string $type, int $created, int $issued, int $anulated, int $deleted) use (&$out): void {
            $out[$key] ??= ['created' => 0, 'issued' => 0, 'anulated' => 0, 'deleted' => 0, 'individual_created' => 0, 'individual_issued' => 0, 'corporate_created' => 0, 'corporate_issued' => 0];
            $out[$key]['created'] += $created;
            $out[$key]['issued'] += $issued;
            $out[$key]['anulated'] += $anulated;
            $out[$key]['deleted'] += $deleted;
            $out[$key][$type.'_created'] += $created;
            $out[$key][$type.'_issued'] += $issued;
        };

        $individual = DB::table('individual_quotes as q')
            ->whereBetween('q.created_at', $range)
            ->whereNotNull("q.{$column}")
            ->groupBy("q.{$column}")
            ->select("q.{$column} as seller")
            ->selectRaw('COUNT(*) as created')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM affiliations a WHERE a.individual_quote_id = q.id AND a.deleted_at IS NULL) THEN 1 ELSE 0 END) as issued')
            ->selectRaw("SUM(CASE WHEN UPPER(TRIM(q.status)) = 'ANULADA' AND NOT EXISTS (SELECT 1 FROM affiliations a WHERE a.individual_quote_id = q.id AND a.deleted_at IS NULL) THEN 1 ELSE 0 END) as anulated")
            ->get();

        foreach ($individual as $row) {
            $key = self::normalizeKey($row->seller, $dimension);

            if ($key !== null) {
                $add($key, 'individual', (int) $row->created, (int) $row->issued, (int) $row->anulated, 0);
            }
        }

        $corporate = DB::table('corporate_quotes as c')
            ->whereBetween('c.created_at', $range)
            ->whereNotNull("c.{$column}")
            ->groupBy("c.{$column}")
            ->select("c.{$column} as seller")
            ->selectRaw('COUNT(*) as created')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM affiliation_corporates ac WHERE ac.corporate_quote_id = c.id AND ac.deleted_at IS NULL) THEN 1 ELSE 0 END) as issued')
            ->get();

        foreach ($corporate as $row) {
            $key = self::normalizeKey($row->seller, $dimension);

            if ($key !== null) {
                $add($key, 'corporate', (int) $row->created, (int) $row->issued, 0, 0);
            }
        }

        $deleted = QuoteDeletionLog::query()
            ->whereBetween('quote_created_at', $range)
            ->whereNotNull($column)
            ->get(['quote_type', $column]);

        foreach ($deleted as $log) {
            $key = self::normalizeKey($log->getAttribute($column), $dimension);

            if ($key !== null) {
                $add($key, $log->quote_type === QuoteDeletionLog::CORPORATE ? 'corporate' : 'individual', 1, 0, 0, 1);
            }
        }

        return $out;
    }

    private static function normalizeKey(mixed $value, string $dimension): ?string
    {
        if ($dimension === self::AGENT) {
            $id = (int) $value;

            return $id > 0 ? (string) $id : null;
        }

        $code = strtoupper(trim((string) $value));

        return $code !== '' ? $code : null;
    }

    /**
     * @param  list<string|int>  $keys
     * @return array<string, array{label: string, sublabel: ?string}>
     */
    private static function labels(string $dimension, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $out = [];

        if ($dimension === self::AGENT) {
            $agents = DB::table('agents')->whereIn('id', array_map('intval', $keys))->get(['id', 'name', 'owner_code', 'status']);

            foreach ($agents as $agent) {
                $sublabel = trim((string) $agent->owner_code);
                $status = strtoupper(trim((string) $agent->status));

                $out[(string) $agent->id] = [
                    'label' => trim((string) $agent->name) !== '' ? trim((string) $agent->name) : 'Agente #'.$agent->id,
                    'sublabel' => trim(($sublabel !== '' ? $sublabel : 'Sin agencia').($status !== '' && $status !== 'ACTIVO' ? ' · '.$status : '')),
                ];
            }

            foreach ($keys as $key) {
                $out[(string) $key] ??= ['label' => 'Agente #'.$key, 'sublabel' => 'ya no existe en la base'];
            }

            return $out;
        }

        $agencies = DB::table('agencies')->whereIn('code', array_map('strval', $keys))->get(['code', 'name_corporative']);

        foreach ($agencies as $agency) {
            $code = strtoupper(trim((string) $agency->code));
            $out[$code] = ['label' => trim((string) $agency->name_corporative) !== '' ? trim((string) $agency->name_corporative) : $code, 'sublabel' => $code];
        }

        foreach ($keys as $key) {
            $key = (string) $key;
            $out[$key] ??= $key === self::HEAD_OFFICE_CODE
                ? ['label' => 'Casa matriz (In House)', 'sublabel' => $key]
                : ['label' => $key, 'sublabel' => 'agencia no registrada'];
        }

        return $out;
    }

    private static function withoutAgent(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $range = CorporateMetrics::range($from, $to);

        $noAgent = fn ($query) => $query->where(fn ($inner) => $inner->whereNull('agent_id')->orWhere('agent_id', 0));

        return $noAgent(DB::table('individual_quotes')->whereBetween('created_at', $range))->count()
            + $noAgent(DB::table('corporate_quotes')->whereBetween('created_at', $range))->count()
            + $noAgent(QuoteDeletionLog::query()->whereBetween('quote_created_at', $range))->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;

/**
 * Análisis de cotizaciones por plan para decidir: cifras de individuales y corporativas por
 * separado, tasas de emisión y de pérdida con su variación, lo que sigue en curso y una lectura
 * automática con reglas fijas sobre esas cifras (no hay estimaciones: todo sale de
 * {@see CorporateQuoteMetrics}).
 */
final class CorporateQuoteAnalysis
{
    /** Por debajo de este tamaño una tasa no dice nada y no genera lectura. */
    public const MIN_SAMPLE = 10;

    /** Variación de creadas que merece mención. */
    public const CHANGE_THRESHOLD = 20.0;

    /** Emisión por debajo de esto es crítica. */
    public const LOW_ISSUANCE_RATE = 3.0;

    /** Pérdida (anuladas + eliminadas) desde esto pide atención. */
    public const HIGH_VOIDED_RATE = 50.0;

    private const TYPE_LABELS = [
        CorporateQuoteMetrics::INDIVIDUAL => 'Individuales',
        CorporateQuoteMetrics::CORPORATE => 'Corporativas',
    ];

    private const TYPE_SINGULAR = [
        CorporateQuoteMetrics::INDIVIDUAL => 'individual',
        CorporateQuoteMetrics::CORPORATE => 'corporativa',
    ];

    /**
     * @return array{
     *     plans: list<array{id: int, label: string, total_created: int, total_previous: int, types: array<string, array<string, mixed>>}>,
     *     totals: array<string, array{current: int, previous: int}>,
     *     unattributed: array{individual: int, corporate: int},
     *     insights: list<array{tone: string, area: string, title: string, text: string, url_key: string}>,
     *     cohort_open: bool,
     *     compare: bool
     * }
     */
    public static function build(Period $period): array
    {
        return CorporateMetrics::remember('commercial_quotes_analysis_'.($period->compare ? 'cmp' : 'solo'), $period, static function () use ($period): array {
            $current = CorporateQuoteMetrics::createdBetween($period->from, $period->to);
            $previous = CorporateQuoteMetrics::createdBetween($period->previousFrom, $period->previousTo);
            $totals = [];

            foreach (array_keys(self::TYPE_LABELS) as $type) {
                $totals[$type] = ['current' => $current[$type], 'previous' => $previous[$type]];
            }

            $plans = [];

            foreach (CorporateQuoteMetrics::plans() as $planId => $label) {
                $types = [];

                foreach (array_keys(self::TYPE_LABELS) as $type) {
                    $data = CorporateQuoteMetrics::plan($period, $planId, $type);
                    $types[$type] = self::block($data['current'], $data['previous'], $totals[$type]['current']);
                }

                $plans[] = [
                    'id' => $planId,
                    'label' => $label,
                    'total_created' => $types[CorporateQuoteMetrics::INDIVIDUAL]['current']['created'] + $types[CorporateQuoteMetrics::CORPORATE]['current']['created'],
                    'total_previous' => $types[CorporateQuoteMetrics::INDIVIDUAL]['previous']['created'] + $types[CorporateQuoteMetrics::CORPORATE]['previous']['created'],
                    'types' => $types,
                ];
            }

            $cohortOpen = $period->to->greaterThanOrEqualTo(CarbonImmutable::today()->subDays(CorporateQuoteMetrics::AUTO_ANULATION_DAYS));

            return [
                'plans' => $plans,
                'totals' => $totals,
                'unattributed' => CorporateQuoteMetrics::unattributed($period),
                'insights' => self::insights($plans, $totals, $period, $cohortOpen),
                'cohort_open' => $cohortOpen,
                'compare' => $period->compare,
            ];
        });
    }

    /**
     * @param  array<string, int>  $current
     * @param  array<string, int>  $previous
     * @return array<string, mixed>
     */
    public static function block(array $current, array $previous, int $typeTotal): array
    {
        $issuedRate = self::rate($current['issued'], $current['created']);
        $previousIssuedRate = self::rate($previous['issued'], $previous['created']);
        $voidedRate = self::rate($current['voided'], $current['created']);
        $previousVoidedRate = self::rate($previous['voided'], $previous['created']);

        return [
            'current' => $current,
            'previous' => $previous,
            'created_change' => CorporateMetrics::change((float) $current['created'], (float) $previous['created']),
            'issued_rate' => $issuedRate,
            'issued_rate_delta' => $issuedRate !== null && $previousIssuedRate !== null ? round($issuedRate - $previousIssuedRate, 1) : null,
            'voided_rate' => $voidedRate,
            'voided_rate_delta' => $voidedRate !== null && $previousVoidedRate !== null ? round($voidedRate - $previousVoidedRate, 1) : null,
            'share' => self::rate($current['created'], $typeTotal),
            'segments' => self::segments($current),
        ];
    }

    public static function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round($part * 100 / $total, 1) : null;
    }

    /**
     * Partes de la barra apilada: suman el total de creadas.
     *
     * @param  array<string, int>  $current
     * @return list<array{key: string, label: string, value: int, percent: float, color: string}>
     */
    private static function segments(array $current): array
    {
        $parts = [
            ['key' => 'issued', 'label' => 'Emitidas', 'value' => $current['issued'], 'color' => 'var(--ops-good)'],
            ['key' => 'open', 'label' => 'En curso', 'value' => $current['open'], 'color' => 'var(--ops-series1)'],
            ['key' => 'anulated', 'label' => 'Anuladas', 'value' => $current['anulated'], 'color' => 'var(--ops-critical)'],
            ['key' => 'deleted', 'label' => 'Eliminadas', 'value' => $current['deleted'], 'color' => 'var(--ops-warning)'],
        ];

        return array_map(static fn (array $part): array => [
            ...$part,
            'percent' => $current['created'] > 0 ? round($part['value'] * 100 / $current['created'], 1) : 0.0,
        ], $parts);
    }

    /**
     * Lectura del período: reglas fijas sobre las cifras, agrupadas por tema y ordenadas de lo
     * más grave a lo informativo. La madurez del período no va aquí: la vista la muestra aparte.
     *
     * @param  list<array<string, mixed>>  $plans
     * @param  array<string, array{current: int, previous: int}>  $totals
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    private static function insights(array $plans, array $totals, Period $period, bool $cohortOpen): array
    {
        $critical = [];
        $voided = [];
        $approved = [];
        $stale = [];
        $changes = [CorporateQuoteMetrics::INDIVIDUAL => [], CorporateQuoteMetrics::CORPORATE => []];
        $corporateCreated = 0;
        $corporateIssued = 0;
        $corporateByPlan = [];
        $bestIssuance = null;

        foreach ($plans as $plan) {
            foreach ($plan['types'] as $type => $block) {
                $c = $block['current'];
                $p = $block['previous'];
                $short = $plan['label'].' '.($type === CorporateQuoteMetrics::INDIVIDUAL ? 'ind.' : 'corp.');

                if ($c['created'] >= self::MIN_SAMPLE && $block['issued_rate'] !== null && $block['issued_rate'] < self::LOW_ISSUANCE_RATE) {
                    $critical[] = self::insight('critical', $plan['label'].' · '.self::TYPE_LABELS[$type], 'Casi no se emite: '.self::pct($block['issued_rate']).' de las creadas',
                        'Solo '.self::n($c['issued']).' de '.self::n($c['created']).' cotizaciones '.self::plural(self::TYPE_SINGULAR[$type]).' ya son afiliación; '
                        .self::n($c['voided']).' se perdieron y '.self::n($c['open']).' siguen en curso.');
                }

                if ($type === CorporateQuoteMetrics::INDIVIDUAL && $c['created'] >= self::MIN_SAMPLE && $block['voided_rate'] !== null && $block['voided_rate'] >= self::HIGH_VOIDED_RATE) {
                    $voided[] = $plan['label'].' '.self::pct($block['voided_rate']).' ('.self::n($c['voided']).' de '.self::n($c['created']).')';
                }

                if ($c['approved'] > 0) {
                    $approved[] = $short.' '.self::n($c['approved']);
                }

                if ($c['stale'] > 0) {
                    $stale[] = $plan['label'].' '.self::n($c['stale']);
                }

                if ($period->compare && $block['created_change'] !== null && max($c['created'], $p['created']) >= 5) {
                    $changes[$type][] = [
                        'label' => $plan['label'],
                        'change' => $block['created_change'],
                        'text' => $plan['label'].' '.self::signedPct($block['created_change']).' ('.self::n($p['created']).' → '.self::n($c['created']).')',
                    ];
                }

                if ($type === CorporateQuoteMetrics::CORPORATE) {
                    $corporateCreated += $c['created'];
                    $corporateIssued += $c['issued'];

                    if ($c['created'] > 0) {
                        $corporateByPlan[] = $plan['label'].' '.self::n($c['created']).' ('.self::n($c['pre_approved']).' pre-aprobadas)';
                    }
                }

                if ($type === CorporateQuoteMetrics::INDIVIDUAL && $c['created'] >= self::MIN_SAMPLE && $block['issued_rate'] !== null
                    && ($bestIssuance === null || $block['issued_rate'] > $bestIssuance['rate'])) {
                    $bestIssuance = ['rate' => $block['issued_rate'], 'label' => $plan['label'], 'issued' => $c['issued'], 'created' => $c['created']];
                }
            }
        }

        $out = $critical;

        if ($approved !== []) {
            $out[] = self::insight('warning', 'Oportunidad de cierre', 'Hay cotizaciones aprobadas sin afiliación',
                implode(' · ', $approved).'. El cliente ya aprobó y falta afiliarlo: es el cierre más cercano.');
        }

        if ($stale !== []) {
            $out[] = self::insight('warning', 'Individuales', 'Pre-aprobadas con más de 15 días sin anular',
                implode(' · ', $stale).'. El sistema las anula a los 15 días sin aprobar y no lo hizo: revise «Anulación de cotizaciones» en el Centro de notificaciones o deles seguimiento. Una múltiple puede aparecer en más de un plan.');
        }

        if ($voided !== []) {
            $out[] = self::insight('warning', 'Individuales', 'Más de la mitad se pierde',
                implode(' · ', $voided).'. Casi todas se anulan por vencimiento: el seguimiento de los primeros 15 días es donde se gana o se pierde la venta.');
        }

        if ($corporateCreated >= 5 && $corporateIssued === 0) {
            $out[] = self::insight('warning', 'Corporativas', 'Ninguna corporativa del período se ha emitido',
                'Ninguna cotización corporativa de Inicial, Ideal o Especial tiene afiliación: '.implode(' · ', $corporateByPlan).'. Una múltiple cuenta en cada plan que cotiza.');
        }

        foreach ($changes as $type => $items) {
            if ($items === []) {
                continue;
            }

            $down = count(array_filter($items, fn (array $item): bool => $item['change'] <= -self::CHANGE_THRESHOLD));
            $up = count(array_filter($items, fn (array $item): bool => $item['change'] >= self::CHANGE_THRESHOLD));

            if ($down === 0 && $up === 0) {
                continue;
            }

            $tone = $down > $up ? 'warning' : ($up > $down ? 'good' : 'neutral');
            $title = match (true) {
                $down === count($items) => 'Bajó lo cotizado en todos los planes',
                $up === count($items) => 'Subió lo cotizado en todos los planes',
                $down > $up => 'Bajó lo cotizado en la mayoría de los planes',
                $up > $down => 'Subió lo cotizado en la mayoría de los planes',
                default => 'Lo cotizado se movió en sentidos opuestos',
            };

            $out[] = self::insight($tone, self::TYPE_LABELS[$type], $title,
                'Frente a '.$period->previousLabel().': '.implode(' · ', array_column($items, 'text')).'.');
        }

        if ($bestIssuance !== null && $bestIssuance['rate'] >= self::LOW_ISSUANCE_RATE) {
            $out[] = self::insight('good', 'Individuales', 'Mejor emisión: '.$bestIssuance['label'].' con '.self::pct($bestIssuance['rate']),
                self::n($bestIssuance['issued']).' de '.self::n($bestIssuance['created']).' cotizaciones del período ya son afiliación.');
        }

        $leader = collect($plans)->sortByDesc(fn (array $plan): int => $plan['types'][CorporateQuoteMetrics::INDIVIDUAL]['current']['created'])->first();
        $leaderShare = $leader['types'][CorporateQuoteMetrics::INDIVIDUAL]['share'] ?? null;

        if ($leaderShare !== null && $totals[CorporateQuoteMetrics::INDIVIDUAL]['current'] >= self::MIN_SAMPLE) {
            $out[] = self::insight('neutral', 'Individuales', $leader['label'].' está en '.self::pct($leaderShare).' de lo cotizado',
                self::n($leader['types'][CorporateQuoteMetrics::INDIVIDUAL]['current']['created']).' de '.self::n($totals[CorporateQuoteMetrics::INDIVIDUAL]['current']).' cotizaciones individuales del período lo incluyen.');
        }

        return $out;
    }

    /**
     * @return array{tone: string, area: string, title: string, text: string, url_key: string}
     */
    private static function insight(string $tone, string $area, string $title, string $text): array
    {
        return ['tone' => $tone, 'area' => $area, 'title' => $title, 'text' => trim($text), 'url_key' => ''];
    }

    private static function n(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private static function pct(float $value): string
    {
        return number_format($value, 1, ',', '.').' %';
    }

    private static function signedPct(float $value): string
    {
        return ($value > 0 ? '+' : '−').number_format(abs($value), 1, ',', '.').' %';
    }

    private static function plural(string $singular): string
    {
        return $singular === 'individual' ? 'individuales' : 'corporativas';
    }
}

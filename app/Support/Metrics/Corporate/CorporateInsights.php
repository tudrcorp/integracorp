<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;

/**
 * Lectura automática del período: convierte las cifras de todas las áreas en
 * frases con un tono (bien, atención, crítico, informativo), ordenadas por
 * urgencia. Son reglas fijas y explicables, no un modelo: cada umbral está aquí.
 */
final class CorporateInsights
{
    public const GOOD = 'good';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const NEUTRAL = 'neutral';

    /** Variación de ingresos (%) que se considera relevante. */
    public const INCOME_SWING = 5.0;

    /** Caída de ingresos (%) que pasa de «atención» a «crítico». */
    public const INCOME_DROP_CRITICAL = 15.0;

    /** Participación de una sola agencia en los ingresos que es un riesgo. */
    public const CONCENTRATION = 35.0;

    /** Siniestralidad estimada (%) de atención y crítica. */
    public const LOSS_WARNING = 40.0;

    public const LOSS_CRITICAL = 60.0;

    /** Tasa de renovación (%) por debajo de la cual se avisa. */
    public const RENEWAL_FLOOR = 70.0;

    /** Cumplimiento de SLA (%) por debajo del cual se avisa. */
    public const SLA_FLOOR = 85.0;

    /** Caída de la conversión (puntos) que se avisa. */
    public const CONVERSION_DROP = 2.0;

    private const ORDER = [self::CRITICAL => 0, self::WARNING => 1, self::GOOD => 2, self::NEUTRAL => 3];

    /**
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    public static function for(Period $period, int $limit = 8): array
    {
        return CorporateMetrics::remember('insights_'.$limit, $period, static function () use ($period, $limit): array {
            $insights = [
                ...self::finance($period),
                ...self::portfolio($period),
                ...self::commercial($period),
                ...self::operations($period),
                ...self::organization($period),
                ...self::users($period),
            ];

            usort($insights, static fn (array $a, array $b): int => self::ORDER[$a['tone']] <=> self::ORDER[$b['tone']]);

            return array_slice($insights, 0, $limit);
        });
    }

    /**
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    private static function finance(Period $period): array
    {
        $out = [];
        $kpis = CorporateFinanceMetrics::kpis($period);
        $current = $kpis['current'];
        $previous = $kpis['previous'];
        $income = (float) $current['income'];

        if ($period->compare && (float) $previous['income'] > 0) {
            $change = (float) CorporateMetrics::change($income, (float) $previous['income']);
            $amounts = CorporateMetrics::money($income).' frente a '.CorporateMetrics::money((float) $previous['income']).' del período anterior.';

            if ($change >= self::INCOME_SWING) {
                $out[] = self::make(self::GOOD, 'Finanzas', 'Los ingresos crecen '.self::percent($change), $amounts, 'finance');
            } elseif ($change <= -self::INCOME_SWING) {
                $tone = $change <= -self::INCOME_DROP_CRITICAL ? self::CRITICAL : self::WARNING;
                $out[] = self::make($tone, 'Finanzas', 'Los ingresos caen '.self::percent(abs($change)), $amounts, 'finance');
            } else {
                $out[] = self::make(self::NEUTRAL, 'Finanzas', 'Ingresos estables', $amounts, 'finance');
            }
        } elseif ($income > 0) {
            $out[] = self::make(self::NEUTRAL, 'Finanzas', 'Ingresos del período: '.CorporateMetrics::money($income), CorporateMetrics::number((int) $current['payments']).' pagos registrados.', 'finance');
        }

        if ($current['loss_ratio'] !== null && $income > 0) {
            $loss = (float) $current['loss_ratio'];
            $notice = CorporateFinanceMetrics::costCoverageNotice($period);
            $tone = $loss >= self::LOSS_CRITICAL ? self::CRITICAL : ($loss >= self::LOSS_WARNING ? self::WARNING : self::GOOD);
            $out[] = self::make(
                $notice !== null && $tone === self::GOOD ? self::NEUTRAL : $tone,
                'Finanzas',
                'Siniestralidad estimada: '.CorporateMetrics::number($loss, 1).' %',
                'El costo médico del período es '.CorporateMetrics::money((float) $current['medical_cost']).' sobre '.CorporateMetrics::money($income).' de ingresos. Margen estimado: '.CorporateMetrics::number((float) $current['margin_rate'], 1).' %.'
                    .($notice !== null ? ' '.$notice : ''),
                'finance',
            );
        }

        $agencies = CorporateFinanceMetrics::incomeByAgency($period, 1);
        $top = $agencies['rows'][0] ?? null;

        if ($top !== null && $agencies['total'] > 0) {
            $share = $top['value'] * 100 / $agencies['total'];

            if ($share >= self::CONCENTRATION) {
                $out[] = self::make(self::WARNING, 'Comercial', 'Ingresos concentrados en una agencia', $top['label'].' aporta el '.CorporateMetrics::number($share, 1).' % de los ingresos del período.', 'finance');
            }
        }

        $receivables = CorporateFinanceMetrics::receivables();

        if ($receivables['overdue_amount'] > 0) {
            $share = $receivables['pending_amount'] > 0 ? $receivables['overdue_amount'] * 100 / $receivables['pending_amount'] : 0;
            $out[] = self::make(
                $share >= 20 ? self::CRITICAL : self::WARNING,
                'Cartera',
                CorporateMetrics::money($receivables['overdue_amount']).' en cuotas vencidas',
                CorporateMetrics::number($receivables['overdue_count']).' cuotas ya pasaron su fecha de pago (a hoy).',
                'portfolio',
            );
        }

        return $out;
    }

    /**
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    private static function portfolio(Period $period): array
    {
        $out = [];
        $current = CorporatePortfolioMetrics::kpis($period)['current'];
        $new = (int) $current['new'];
        $dropouts = (int) $current['dropouts'];

        if ($new > 0 || $dropouts > 0) {
            $net = $new - $dropouts;
            $text = CorporateMetrics::number($new).' altas y '.CorporateMetrics::number($dropouts).' bajas en el período.';

            $out[] = $net >= 0
                ? self::make(self::GOOD, 'Cartera', 'La cartera crece en '.self::count($net, 'afiliación', 'afiliaciones'), $text, 'portfolio')
                : self::make(self::CRITICAL, 'Cartera', 'La cartera se reduce en '.self::count(abs($net), 'afiliación', 'afiliaciones'), $text, 'portfolio');
        }

        if ($current['renewal_rate'] !== null) {
            $rate = (float) $current['renewal_rate'];
            $out[] = self::make(
                $rate < self::RENEWAL_FLOOR ? self::WARNING : self::GOOD,
                'Cartera',
                'Renovación: '.CorporateMetrics::number($rate, 1).' %',
                CorporateMetrics::number((int) $current['renewed']).' renovadas y '.CorporateMetrics::number((int) $current['expired']).' vencidas sin renovar en el período.',
                'portfolio',
            );
        }

        return $out;
    }

    /**
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    private static function commercial(Period $period): array
    {
        $kpis = CorporateCommercialMetrics::kpis($period);
        $current = $kpis['current'];
        $previous = $kpis['previous'];

        if ($current['conversion'] === null) {
            return [];
        }

        $text = CorporateMetrics::number((int) $current['executed']).' de '.CorporateMetrics::number((int) $current['individual_quotes']).' cotizaciones individuales ya son afiliaciones.';

        if ($period->compare && $previous['conversion'] !== null && (float) $current['conversion'] <= (float) $previous['conversion'] - self::CONVERSION_DROP) {
            return [self::make(self::WARNING, 'Comercial', 'La conversión baja a '.CorporateMetrics::number((float) $current['conversion'], 1).' %', $text.' En el período anterior era '.CorporateMetrics::number((float) $previous['conversion'], 1).' %.', 'commercial')];
        }

        return [self::make(self::NEUTRAL, 'Comercial', 'Conversión de cotizaciones: '.CorporateMetrics::number((float) $current['conversion'], 1).' %', $text, 'commercial')];
    }

    /**
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    private static function operations(Period $period): array
    {
        $current = OperationsDashboardAnalytics::kpis($period)['current'];

        if ((int) $current['services'] === 0) {
            return [];
        }

        $rate = (float) $current['close_rate'];

        return [self::make(
            $rate < 60 ? self::WARNING : self::NEUTRAL,
            'Operaciones',
            CorporateMetrics::number((int) $current['services']).' servicios médicos, '.CorporateMetrics::number($rate, 1).' % cerrados',
            CorporateMetrics::number((int) $current['open']).' siguen pendientes o en gestión; '.CorporateMetrics::number((int) $current['patients']).' pacientes atendidos.',
            'operations',
        )];
    }

    /**
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    private static function organization(Period $period): array
    {
        $kpis = CorporateOrganizationMetrics::kpis($period);
        $current = $kpis['current'];

        if ($current['sla_rate'] === null) {
            return [];
        }

        $rate = (float) $current['sla_rate'];

        return [self::make(
            $rate < self::SLA_FLOOR ? self::WARNING : self::GOOD,
            'Organización',
            'Helpdesk: '.CorporateMetrics::number($rate, 1).' % dentro de SLA',
            CorporateMetrics::number((int) $current['created']).' tickets nuevos, '.CorporateMetrics::number((int) $current['resolved']).' resueltos; '.CorporateMetrics::number($kpis['open']).' abiertos hoy.',
            'organization',
        )];
    }

    /**
     * Las dos lecturas más urgentes de Usuarios (las de solo contexto no suben al resumen).
     *
     * @return list<array{tone: string, area: string, title: string, text: string, url_key: string}>
     */
    private static function users(Period $period): array
    {
        $insights = array_filter(CorporateUsersMetrics::insights($period), static fn (array $insight): bool => $insight['tone'] !== self::NEUTRAL);

        return array_slice(array_values($insights), 0, 2);
    }

    /**
     * Porcentaje como en las tarjetas: un decimal solo por debajo de 10.
     */
    private static function percent(float $value): string
    {
        return CorporateMetrics::number($value, abs($value) < 10 ? 1 : 0).' %';
    }

    private static function count(int $value, string $singular, string $plural): string
    {
        return CorporateMetrics::number($value).' '.($value === 1 ? $singular : $plural);
    }

    /**
     * @return array{tone: string, area: string, title: string, text: string, url_key: string}
     */
    private static function make(string $tone, string $area, string $title, string $text, string $urlKey): array
    {
        return ['tone' => $tone, 'area' => $area, 'title' => $title, 'text' => $text, 'url_key' => $urlKey];
    }
}

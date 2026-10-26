<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Metrics\Pages\Cartera;
use App\Filament\Metrics\Pages\Comercial;
use App\Filament\Metrics\Pages\Finanzas;
use App\Filament\Metrics\Pages\OperacionesYSalud;
use App\Filament\Metrics\Pages\Organizacion;
use App\Filament\Metrics\Pages\Usuarios;
use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles;
use App\Support\Metrics\Corporate\CorporateCommercialMetrics;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use Filament\Widgets\Widget;
use Throwable;

/**
 * Pulso de cada área: su cifra principal con la variación y dos cifras de
 * apoyo. Cada tarjeta lleva a la página del área.
 */
class CorporateAreaPulse extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.area-pulse';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $finance = CorporateFinanceMetrics::kpis($period);
        $commercial = CorporateCommercialMetrics::kpis($period);
        $portfolio = CorporatePortfolioMetrics::kpis($period);
        $operations = OperationsDashboardAnalytics::kpis($period);
        $organization = CorporateOrganizationMetrics::kpis($period);
        $snapshot = CorporatePortfolioMetrics::snapshot();
        $receivables = CorporateFinanceMetrics::receivables();
        $users = CorporateUsersMetrics::kpis($period);

        $areas = [
            self::area('finance', Finanzas::class, 'Finanzas', 'heroicon-o-banknotes', 'Ingresos', CorporateMetrics::money((float) $finance['current']['income']), (float) $finance['current']['income'], (float) $finance['previous']['income'], false, [
                ['Margen estimado', CorporateMetrics::money((float) $finance['current']['margin'])],
                ['Por cobrar hoy', CorporateMetrics::money($receivables['pending_amount'])],
            ]),
            self::area('commercial', Comercial::class, 'Comercial', 'heroicon-o-presentation-chart-line', 'Cotizaciones', CorporateMetrics::number((int) $commercial['current']['quotes']), (float) $commercial['current']['quotes'], (float) $commercial['previous']['quotes'], false, [
                ['Conversión', $commercial['current']['conversion'] === null ? '—' : CorporateMetrics::number((float) $commercial['current']['conversion'], 1).' %'],
                ['Agentes nuevos', CorporateMetrics::number((int) $commercial['current']['new_agents'])],
            ]),
            self::area('portfolio', Cartera::class, 'Cartera', 'heroicon-o-identification', 'Altas', CorporateMetrics::number((int) $portfolio['current']['new']), (float) $portfolio['current']['new'], (float) $portfolio['previous']['new'], false, [
                ['Bajas', CorporateMetrics::number((int) $portfolio['current']['dropouts'])],
                ['Activas hoy', CorporateMetrics::number($snapshot['active'])],
            ]),
            self::area('operations', OperacionesYSalud::class, 'Operaciones y salud', 'heroicon-o-heart', 'Servicios médicos', CorporateMetrics::number((int) $operations['current']['services']), (float) $operations['current']['services'], (float) $operations['previous']['services'], false, [
                ['Tasa de cierre', CorporateMetrics::number((float) $operations['current']['close_rate'], 1).' %'],
                ['Pacientes', CorporateMetrics::number((int) $operations['current']['patients'])],
            ]),
            self::area('organization', Organizacion::class, 'Organización', 'heroicon-o-lifebuoy', 'Tickets nuevos', CorporateMetrics::number((int) $organization['current']['created']), (float) $organization['current']['created'], (float) $organization['previous']['created'], true, [
                ['Dentro de SLA', $organization['current']['sla_rate'] === null ? '—' : CorporateMetrics::number((float) $organization['current']['sla_rate'], 1).' %'],
                ['Abiertos hoy', CorporateMetrics::number($organization['open'])],
            ]),
            self::area('users', Usuarios::class, 'Usuarios', 'heroicon-o-users', 'Horas activas', CorporateMetrics::number((float) $users['current']['hours'], 1).' h', (float) $users['current']['hours'], (float) $users['previous']['hours'], false, [
                ['Uso de la jornada', $users['current']['work_usage'] === null ? '—' : CorporateMetrics::number((float) $users['current']['work_usage'], 1).' %'],
                ['Conectadas hoy', CorporateMetrics::number($users['snapshot']['online_today'])],
            ]),
        ];

        return [
            'areas' => $areas,
            'compare' => $period->compare,
            'periodKey' => $period->cacheKey(),
        ];
    }

    /**
     * @param  class-string  $page
     * @param  list<array{0: string, 1: string}>  $facts
     * @return array<string, mixed>
     */
    private static function area(string $key, string $page, string $label, string $icon, string $metric, string $display, float $value, float $previous, bool $lowerIsBetter, array $facts): array
    {
        $delta = OperationsKpiTiles::delta($value, $previous);

        try {
            $url = $page::canAccess() ? $page::getUrl() : null;
        } catch (Throwable) {
            $url = null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'metric' => $metric,
            'display' => $display,
            'delta' => $delta,
            'improved' => $delta['direction'] === 'flat' ? null : (($delta['direction'] === 'up') !== $lowerIsBetter),
            'facts' => $facts,
            'url' => $url,
        ];
    }
}

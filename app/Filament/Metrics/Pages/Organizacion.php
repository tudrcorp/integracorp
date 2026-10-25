<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Metrics\Pages\Concerns\IsCorporateMetricsPage;
use App\Filament\Metrics\Widgets\Corporate\CorporateDetailWidget;
use App\Filament\Metrics\Widgets\Corporate\OrganizationKpiTiles;
use App\Filament\Metrics\Widgets\Corporate\TicketsAreaChart;
use App\Filament\Metrics\Widgets\Corporate\TicketsFlowChart;
use App\Filament\Metrics\Widgets\Corporate\TicketsPriorityChart;
use App\Filament\Metrics\Widgets\Corporate\TicketsStatusChart;
use App\Filament\Metrics\Widgets\Corporate\TicketsTypeChart;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Organización: la gestión interna vista desde el helpdesk (sin RRHH ni
 * nómina, por decisión de negocio).
 */
class Organizacion extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use IsCorporateMetricsPage;

    protected static ?string $navigationLabel = 'Organización';

    protected static ?string $title = 'Organización';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 6;

    protected static string $routePath = 'organizacion';

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            OrganizationKpiTiles::class,
            TicketsFlowChart::class,
            TicketsStatusChart::class,
            TicketsPriorityChart::class,
            TicketsTypeChart::class,
            TicketsAreaChart::class,
            CorporateDetailWidget::class,
        ];
    }
}

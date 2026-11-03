<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Filament\Business\Widgets\Captacion\CaptacionAssignmentsChart;
use App\Filament\Business\Widgets\Captacion\CaptacionClassificationChart;
use App\Filament\Business\Widgets\Captacion\CaptacionCollaboratorRanking;
use App\Filament\Business\Widgets\Captacion\CaptacionDetail;
use App\Filament\Business\Widgets\Captacion\CaptacionKpiTiles;
use App\Filament\Business\Widgets\Captacion\CaptacionReferenceChart;
use App\Filament\Business\Widgets\Captacion\CaptacionRegistrationsTrend;
use App\Filament\Business\Widgets\Captacion\CaptacionStateChart;
use App\Filament\Business\Widgets\Captacion\CaptacionTypeChart;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Pages\Concerns\HasCompactDashboardHeader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Dashboard de Captación: los mismos indicadores y gráficos que estaban sobre la tabla de
 * prospectos, con el lenguaje de los dashboards nuevos (un período para toda la página,
 * comparación, detalle al clic y modo presentación).
 *
 * Usa el permiso de Captación: quien ve los prospectos ve su dashboard. No va en el menú:
 * se abre con el botón «Ver dashboard» de la lista de prospectos.
 */
class DashboardCaptacion extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use HasCompactDashboardHeader;

    protected static ?string $navigationLabel = 'Dashboard de Captación';

    protected static ?string $title = 'Dashboard de Captación';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 7;

    protected static string $routePath = 'dashboard-captacion';

    /**
     * Fuera del menú (pedido de Negocios): se entra desde «Ver dashboard» en Captación.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'El período mueve las altas, los gráficos y las tareas; el embudo por estatus es a hoy. Haga clic en una barra o fila para ver los prospectos.';
    }

    protected function headerEyebrow(): string
    {
        return 'Captación';
    }

    protected function headerEyebrowIcon(): string
    {
        return 'heroicon-m-user-plus';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('prospects')
                ->label('Ver prospectos')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(ProspectAgentResource::getUrl('index'))
                ->extraAttributes(['class' => 'ops-hide-in-presentation']),
            $this->presentationAction(),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            CaptacionKpiTiles::class,
            CaptacionRegistrationsTrend::class,
            CaptacionCollaboratorRanking::class,
            CaptacionStateChart::class,
            CaptacionTypeChart::class,
            CaptacionReferenceChart::class,
            CaptacionClassificationChart::class,
            CaptacionAssignmentsChart::class,
            CaptacionDetail::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 12];
    }
}

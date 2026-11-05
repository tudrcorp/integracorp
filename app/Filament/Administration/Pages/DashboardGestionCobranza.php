<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Filament\Administration\Widgets\Collections\CollectionManagementKpis;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Pages\Concerns\HasCompactDashboardHeader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use UnitEnum;

/**
 * Dashboard de Gestión de Cobranza: lo cobrado (mes y año), la morosidad y la cartera por
 * vencer de la tabla de Gestión de Cobranza, con el lenguaje de los dashboards nuevos.
 *
 * Usa el permiso de Gestión de Cobranza. No va en el menú: se abre con el botón «Ver KPIs»
 * de Gestión de Cobranza.
 */
class DashboardGestionCobranza extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use HasCompactDashboardHeader;

    protected static ?string $navigationLabel = 'Dashboard de Gestión de Cobranza';

    protected static ?string $title = 'Dashboard de Gestión de Cobranza';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static string $routePath = 'dashboard-gestion-cobranza';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function headerEyebrow(): string
    {
        return 'Gestión de Cobranza';
    }

    protected function headerEyebrowIcon(): string
    {
        return 'heroicon-m-banknotes';
    }

    /**
     * Cartera a hoy y cobrado del mes y año en curso: sin filtro de período en el encabezado.
     */
    public function getHeader(): ?View
    {
        return view('filament.operations.dashboard.page-header', [
            'eyebrow' => $this->headerEyebrow(),
            'eyebrowIcon' => $this->headerEyebrowIcon(),
            'heading' => $this->getHeading(),
            'period' => null,
            'snapshotLabel' => 'A hoy · '.now()->format('d/m/Y'),
            'hint' => 'Clic en una tarjeta de cartera para verla en Gestión de Cobranza',
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('collections')
                ->label('Ver gestión de cobranza')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(CollectionResource::getUrl('index'))
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
            CollectionManagementKpis::class,
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

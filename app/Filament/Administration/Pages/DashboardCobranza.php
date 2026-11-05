<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Filament\Administration\Widgets\Collections\CollectionReceivablesKpis;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Pages\Concerns\HasCompactDashboardHeader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use UnitEnum;

/**
 * Dashboard de Cobranza: los KPIs de cuentas por cobrar que estaban sobre la tabla de
 * Cuentas por cobrar (Cobranza Por Mes), con el lenguaje de los dashboards nuevos. Aquí se
 * irán sumando los demás indicadores de la cobranza.
 *
 * Usa el permiso de Cobranza Por Mes: quien ve las cuentas por cobrar ve su dashboard. No va
 * en el menú: se abre con el botón «Ver KPIs» de Cuentas por cobrar (como Captación).
 */
class DashboardCobranza extends Dashboard
{
    use AuthorizesDepartmentNavigation;
    use HasCompactDashboardHeader;

    protected static ?string $navigationLabel = 'Dashboard de Cobranza';

    protected static ?string $title = 'Dashboard de Cobranza';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static string $routePath = 'dashboard-cobranza';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function headerEyebrow(): string
    {
        return 'Cobranza';
    }

    protected function headerEyebrowIcon(): string
    {
        return 'heroicon-m-banknotes';
    }

    /**
     * Los KPIs son una foto a hoy: sin filtro de período en el encabezado.
     */
    public function getHeader(): ?View
    {
        return view('filament.operations.dashboard.page-header', [
            'eyebrow' => $this->headerEyebrow(),
            'eyebrowIcon' => $this->headerEyebrowIcon(),
            'heading' => $this->getHeading(),
            'period' => null,
            'snapshotLabel' => 'A hoy · '.now()->format('d/m/Y'),
            'hint' => 'Clic en una cifra para verla en Cuentas por cobrar',
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('receivables')
                ->label('Ver cuentas por cobrar')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(AnnualCollectionResource::getUrl('index'))
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
            CollectionReceivablesKpis::class,
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

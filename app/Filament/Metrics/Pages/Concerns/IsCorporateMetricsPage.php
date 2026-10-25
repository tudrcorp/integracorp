<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages\Concerns;

use App\Filament\Metrics\Pages\Dashboard;
use App\Filament\Operations\Pages\Concerns\HasCompactDashboardHeader;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Lo común a las páginas del panel corporativo de Métricas: el filtro de
 * período (que leen todos los widgets), la comparación con el período
 * anterior, el modo presentación, el botón para volver al resumen y la grilla
 * de 12 columnas.
 *
 * Mismo lenguaje visual que el Dashboard de Operaciones (tokens de color,
 * esqueletos de carga y transiciones en `filament.operations.dashboard.page-start`).
 */
trait IsCorporateMetricsPage
{
    use HasCompactDashboardHeader;

    /**
     * Sin grupo: el panel usa navegación superior y las páginas van como
     * enlaces directos en la barra de arriba.
     */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->periodSubheading();
    }

    protected function headerEyebrow(): string
    {
        return 'Panel corporativo';
    }

    protected function headerEyebrowIcon(): string
    {
        return 'heroicon-m-presentation-chart-bar';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if (static::class === Dashboard::class) {
            return [$this->presentationAction()];
        }

        return [
            Action::make('backToSummary')
                ->label('Volver al resumen')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(Dashboard::getUrl()),
            $this->presentationAction(),
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 12,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages\Concerns;

use App\Filament\Metrics\Pages\Cartera;
use App\Filament\Metrics\Pages\Comercial;
use App\Filament\Metrics\Pages\Dashboard;
use App\Filament\Metrics\Pages\Finanzas;
use App\Filament\Metrics\Pages\OperacionesYSalud;
use App\Filament\Metrics\Pages\Organizacion;
use App\Filament\Operations\Pages\Concerns\HasOperationsDashboardFilters;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Lo común a las páginas del panel corporativo de Métricas: el filtro de
 * período (que leen todos los widgets), la comparación con el período
 * anterior, el modo presentación y la grilla de 12 columnas.
 *
 * Mismo lenguaje visual que el Dashboard de Operaciones (tokens de color,
 * esqueletos de carga y transiciones en `filament.operations.dashboard.page-start`).
 */
trait IsCorporateMetricsPage
{
    use HasOperationsDashboardFilters;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'PANEL CORPORATIVO';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->periodSubheading();
    }

    /**
     * Páginas del panel corporativo, en el orden de las pestañas.
     *
     * @var list<class-string>
     */
    public const CORPORATE_PAGES = [
        Dashboard::class,
        Finanzas::class,
        Comercial::class,
        Cartera::class,
        OperacionesYSalud::class,
        Organizacion::class,
    ];

    /**
     * Pestañas arriba de cada página: se pasa de un área a otra (o se vuelve al
     * resumen) sin abrir el menú, también en modo presentación.
     *
     * @return array<NavigationItem>
     */
    public function getSubNavigation(): array
    {
        $items = [];

        foreach (self::CORPORATE_PAGES as $sort => $page) {
            if (! $page::canAccess()) {
                continue;
            }

            $items[] = NavigationItem::make($page::getNavigationLabel())
                ->icon($page::getNavigationIcon())
                ->url($page::getUrl())
                ->isActiveWhen(fn (): bool => static::class === $page)
                ->sort($sort);
        }

        return $items;
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
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

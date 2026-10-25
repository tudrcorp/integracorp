<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages\Concerns;

use App\Filament\Metrics\Pages\Dashboard;
use App\Filament\Operations\Pages\Concerns\HasOperationsDashboardFilters;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
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
    use HasOperationsDashboardFilters;

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

    /**
     * Encabezado propio: contexto y período a la izquierda; filtros compactos y
     * acciones a la derecha, en una sola barra (en vez del formulario suelto).
     */
    public function getHeader(): ?View
    {
        return view('filament.metrics.corporate.page-header', [
            'heading' => $this->getHeading(),
            'period' => OperationsDashboardPeriod::fromFilters($this->filters),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * Los filtros viven en el encabezado: el contenido solo lleva los widgets.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getWidgetsContentComponent()]);
    }

    /**
     * Versión compacta del filtro de período para la barra del encabezado.
     */
    public function filtersForm(Schema $schema): Schema
    {
        /** Una sola columna: la grilla de varias columnas que Filament da al filtro estira la tarjeta a todo el ancho. */
        return $schema->columns(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 1, 'xl' => 1, '2xl' => 1])->components([
            Flex::make([
                Select::make('period')
                    ->hiddenLabel()
                    ->prefixIcon('heroicon-m-calendar-days')
                    ->options(OperationsDashboardPeriod::options())
                    ->default(OperationsDashboardPeriod::DEFAULT)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live()
                    ->grow(false)
                    ->extraFieldWrapperAttributes(['class' => 'w-60 whitespace-nowrap']),
                Toggle::make('compare')
                    ->label('Comparar')
                    ->default(true)
                    ->inline()
                    ->live()
                    ->grow(false),
            ])->verticallyAlignCenter()->from('sm'),
            Flex::make([
                DatePicker::make('from')
                    ->hiddenLabel()
                    ->placeholder('Desde')
                    ->prefix('Desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->live(),
                DatePicker::make('to')
                    ->hiddenLabel()
                    ->placeholder('Hasta')
                    ->prefix('Hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->live(),
            ])
                ->from('sm')
                ->visible(fn (Get $get): bool => $get('period') === OperationsDashboardPeriod::CUSTOM),
        ]);
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

<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages\Concerns;

use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

/**
 * Encabezado compacto de los dashboards (Operaciones y panel corporativo de
 * Métricas): contexto y período a la izquierda; filtro de período compacto y
 * acciones a la derecha, en una sola barra, en lugar del formulario suelto
 * debajo del título.
 */
trait HasCompactDashboardHeader
{
    use HasOperationsDashboardFilters;

    /**
     * Rótulo pequeño sobre el título («Panel corporativo», «Operaciones»).
     */
    protected function headerEyebrow(): string
    {
        return 'Dashboard';
    }

    protected function headerEyebrowIcon(): string
    {
        return 'heroicon-m-chart-bar-square';
    }

    /**
     * Encabezado propio: contexto y período a la izquierda; filtros compactos y
     * acciones a la derecha, en una sola barra (en vez del formulario suelto).
     */
    public function getHeader(): ?View
    {
        return view('filament.operations.dashboard.page-header', [
            'eyebrow' => $this->headerEyebrow(),
            'eyebrowIcon' => $this->headerEyebrowIcon(),
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
}

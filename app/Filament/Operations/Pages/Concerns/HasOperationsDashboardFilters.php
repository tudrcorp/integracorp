<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages\Concerns;

use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Lo común a los dashboards de Operaciones y del Proveedor: el filtro de período
 * (que leen todos los widgets), el rango en el subtítulo y el modo presentación.
 */
trait HasOperationsDashboardFilters
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
            ->components([
                Select::make('period')
                    ->label('Período')
                    ->options(OperationsDashboardPeriod::options())
                    ->default(OperationsDashboardPeriod::DEFAULT)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),
                DatePicker::make('from')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->visible(fn (Get $get): bool => $get('period') === OperationsDashboardPeriod::CUSTOM)
                    ->live(),
                DatePicker::make('to')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->visible(fn (Get $get): bool => $get('period') === OperationsDashboardPeriod::CUSTOM)
                    ->live(),
                Toggle::make('compare')
                    ->label('Comparar con el período anterior')
                    ->default(true)
                    ->inline(false)
                    ->live(),
            ]);
    }

    protected function periodSubheading(): string
    {
        $period = OperationsDashboardPeriod::fromFilters($this->filters);

        return $period->label()
            .($period->compare ? ' · comparado con '.$period->previousLabel() : '')
            .' · Haga clic en cualquier gráfico para ver el detalle.';
    }

    protected function presentationAction(): Action
    {
        return Action::make('presentation')
            ->label('Modo presentación')
            ->icon(Heroicon::OutlinedPresentationChartLine)
            ->color('gray')
            ->tooltip('Pantalla completa, sin menú y con actualización automática cada 2 minutos. Esc para salir.')
            ->alpineClickHandler('window.opsDashboardPresentation?.toggle()');
    }
}

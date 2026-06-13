<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno\Pages;

use App\Filament\Operations\Resources\IndicadoresDeDesempeno\IndicadoresDeDesempenoResource;
use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\ColaboradorActivitiesSpeedometerWidget;
use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Widgets\IndicadoresDeDesempenoChartsTabsWidget;
use App\Http\Controllers\IndicadoresDeDesempenoExportCsvController;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;

class ListIndicadoresDeDesempeno extends Page
{
    protected static string $resource = IndicadoresDeDesempenoResource::class;

    protected static ?string $title = 'Indicadores de desempeño';

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            ColaboradorActivitiesSpeedometerWidget::class,
            IndicadoresDeDesempenoChartsTabsWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Exportar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->modalHeading('Exportar indicadores de desempeño')
                ->modalDescription('Selecciona el intervalo de fechas para exportar la misma información mostrada en los gráficos.')
                ->modalSubmitActionLabel('Exportar')
                ->form([
                    DatePicker::make('desde')
                        ->label('Desde')
                        ->required()
                        ->default(now()->startOfYear()->toDateString())
                        ->maxDate(now()),
                    DatePicker::make('hasta')
                        ->label('Hasta')
                        ->required()
                        ->default(now()->toDateString())
                        ->maxDate(now()),
                ])
                ->action(function (array $data) {
                    $from = (string) ($data['desde'] ?? '');
                    $to = (string) ($data['hasta'] ?? '');

                    if ($from === '' || $to === '') {
                        Notification::make()
                            ->danger()
                            ->title('Intervalo incompleto')
                            ->body('Debes indicar la fecha inicial y final del período.')
                            ->send();

                        return;
                    }

                    if ($from > $to) {
                        Notification::make()
                            ->danger()
                            ->title('Intervalo inválido')
                            ->body('La fecha inicial no puede ser posterior a la fecha final.')
                            ->send();

                        return;
                    }

                    $token = IndicadoresDeDesempenoExportCsvController::storePeriodAndGetToken($from, $to);

                    return redirect()->route('operations.indicadores-de-desempeno.export-csv', ['token' => $token]);
                }),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? 'Indicadores de desempeño';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make($this->getColumns())
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets())),
            ]);
    }
}

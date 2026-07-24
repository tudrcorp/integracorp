<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Pages;

use App\Filament\Administration\Resources\RrhhNominas\RrhhNominaResource;
use App\Http\Controllers\ApiBcvController;
use App\Support\BcvOfficialRate;
use App\Support\Filament\FilamentIosButton;
use App\Support\Rrhh\RrhhNominaCalculator;
use App\Support\Rrhh\RrhhNominaPeriodo;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;

class ListRrhhNominas extends ListRecords
{
    protected static string $resource = RrhhNominaResource::class;

    protected static ?string $title = 'Cálculos de Nómina';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calcularNomina')
                ->label('Calcular nómina')
                ->icon('heroicon-m-calculator')
                ->color('primary')
                ->modalHeading('Calcular nómina')
                ->modalDescription('Seleccione el período quincenal (24 al año) y la tasa BCV. El sueldo del período es la mitad del sueldo mensual.')
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel('Ejecutar cálculo')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->form([
                    Select::make('anio')
                        ->label('Año')
                        ->options(fn (): array => RrhhNominaPeriodo::yearOptions())
                        ->default(fn (): int => (int) now()->year)
                        ->required()
                        ->live()
                        ->native(false),
                    Select::make('periodo')
                        ->label('Período de nómina')
                        ->options(fn (Get $get): array => RrhhNominaPeriodo::optionsForYear(
                            (int) ($get('anio') ?: now()->year)
                        ))
                        ->default(fn (): int => RrhhNominaPeriodo::currentPeriodNumber())
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->helperText('Hay 2 períodos por mes (1–15 y 16–fin de mes). Ejemplo enero: P01 01/01–15/01 y P02 16/01–fin de mes.'),
                    TextInput::make('tasa_bcv')
                        ->label('Tasa BCV de pago')
                        ->numeric()
                        ->required()
                        ->minValue(0.0001)
                        ->step(0.0001)
                        ->prefix('VES')
                        ->suffix('/USD')
                        ->default(fn (): ?float => BcvOfficialRate::resolve())
                        ->helperText('Puede cargar la tasa oficial del BCV o ingresarla manualmente.')
                        ->hintAction(
                            Action::make('cargarTasaBcv')
                                ->label('Cargar tasa BCV')
                                ->icon('heroicon-m-arrow-path')
                                ->action(function (Set $set): void {
                                    $fetched = ApiBcvController::getTasaBcv();
                                    $rate = is_numeric($fetched) ? (float) $fetched : null;

                                    if ($rate === null || $rate <= 0) {
                                        Notification::make()
                                            ->title('No se pudo obtener la tasa BCV')
                                            ->body('Intente nuevamente o cargue la tasa manualmente.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    $set('tasa_bcv', $rate);

                                    Notification::make()
                                        ->title('Tasa BCV cargada')
                                        ->body('Se aplicó la tasa oficial: '.number_format($rate, 4, '.', ',').' VES/USD.')
                                        ->success()
                                        ->send();
                                })
                        ),
                ])
                ->action(function (array $data, RrhhNominaCalculator $calculator): void {
                    try {
                        $nomina = $calculator->calculate($data);

                        Notification::make()
                            ->title('Nómina calculada')
                            ->body('Período '.$nomina->periodoLabel().'. Neto USD$ '.number_format((float) $nomina->total_neto, 2, '.', ',').' / VES '.number_format((float) $nomina->total_neto_ves, 2, '.', ',').'.')
                            ->success()
                            ->send();
                    } catch (\Throwable $th) {
                        Notification::make()
                            ->title('Error al calcular la nómina')
                            ->body($th->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

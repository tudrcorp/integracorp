<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Actions;

use App\Services\PatientSiniestralidadReportService;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

final class ReportSiniestralidadAction
{
    public static function make(): Action
    {
        return Action::make('report_siniestralidad')
            ->label('Reporte siniestralidad')
            ->icon(Heroicon::OutlinedChartBar)
            ->color('warning')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
            ])
            ->visible(fn (): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst())
            ->modalHeading('Reporte de siniestralidad por paciente')
            ->modalDescription('Ranking por cantidad de servicios FINALIZADO. Incluye el monto total facturado (bill_price) para ver el costo de cada paciente. Solo analistas TDG.')
            ->modalWidth(Width::Large)
            ->modalIcon(Heroicon::OutlinedChartBar)
            ->modalIconColor('warning')
            ->modalSubmitActionLabel('Generar reporte')
            ->fillForm([
                'top_n' => PatientSiniestralidadReportService::DEFAULT_TOP_N,
                'format' => 'pdf',
            ])
            ->form([
                Section::make('Parámetros del reporte')
                    ->description('El Top N se calcula por mayor cantidad de siniestros. El monto es informativo (costo para la empresa).')
                    ->schema([
                        TextInput::make('top_n')
                            ->label('Cantidad del ranking (Top N)')
                            ->helperText('Por defecto 50. Puedes cambiarlo al generar el reporte.')
                            ->numeric()
                            ->required()
                            ->minValue(PatientSiniestralidadReportService::MIN_TOP_N)
                            ->maxValue(PatientSiniestralidadReportService::MAX_TOP_N)
                            ->default(PatientSiniestralidadReportService::DEFAULT_TOP_N),
                        DatePicker::make('date_from')
                            ->label('Desde (opcional)')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('date_to')
                            ->label('Hasta (opcional)')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('date_from'),
                        Radio::make('format')
                            ->label('Tipo de documento')
                            ->options([
                                'pdf' => 'PDF (abre vista previa automáticamente)',
                                'csv' => 'CSV (descarga el archivo)',
                            ])
                            ->descriptions([
                                'pdf' => 'Se abrirá la vista previa del PDF en una pestaña nueva.',
                                'csv' => 'Se descargará el archivo CSV de inmediato.',
                            ])
                            ->default('pdf')
                            ->required()
                            ->inline()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->action(function (array $data, Action $action): mixed {
                if (! OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
                    Notification::make()
                        ->danger()
                        ->title('Acceso denegado')
                        ->body('Solo los analistas TDG pueden generar este reporte.')
                        ->send();

                    return null;
                }

                $token = PatientSiniestralidadReportService::storeParamsAndGetToken([
                    'top_n' => $data['top_n'] ?? PatientSiniestralidadReportService::DEFAULT_TOP_N,
                    'date_from' => $data['date_from'] ?? null,
                    'date_to' => $data['date_to'] ?? null,
                ]);

                $format = (string) ($data['format'] ?? 'pdf');

                if ($format === 'csv') {
                    return redirect()->route('operations.telemedicine-patients.siniestralidad.csv', [
                        'token' => $token,
                    ]);
                }

                $previewUrl = route('operations.telemedicine-patients.siniestralidad.preview', [
                    'token' => $token,
                ]);

                $action->getLivewire()->js('window.open('.Js::from($previewUrl).', "_blank")');

                Notification::make()
                    ->success()
                    ->title('Vista previa PDF generada')
                    ->body('Se abrió automáticamente la vista previa del reporte de siniestralidad.')
                    ->send();

                return null;
            });
    }
}

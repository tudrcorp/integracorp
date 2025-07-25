<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LogController;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class ListCorporateQuoteRequests extends ListRecords
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Solicitud de Cotización Corporativa';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crea solicitud')
                ->icon('heroicon-s-plus'),
            Action::make('download_csv')
                ->label('Descargar Archivo CSV')
                ->color('verde')
                ->icon('heroicon-o-arrow-down-tray')
                ->modalIcon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->modalHeading('Descargar Archivo CSV')
                ->modalDescription('El archivo contiene las información necesarias para la carga de la población y sus datos principales. Después de recopilar la data debe ser guardado en formato .CSV(Comas separadas), para luego importar al sistema.')
                ->modalSubmitActionLabel('Desgarcar Archivo')
                ->action(function (array $data) {
                    try {

                        /**
                         * Descargar el documento asociado a la cotizacion
                         * ruta: storage/
                         */
                        $path = public_path('storage/poblacion_ejemplo.xlsx');
                        return response()->download($path);

                        /**
                         * LOG
                         */
                        LogController::log(Auth::user()->id, 'Descarga de documento', 'Modulo Cotizacion Individual', 'DESCARGAR');
                        
                    } catch (\Throwable $th) {
                        LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                        Notification::make()
                            ->title('ERROR')
                            ->body($th->getMessage())
                            ->icon('heroicon-s-x-circle')
                            ->iconColor('danger')
                            ->danger()
                            ->send();
                    }
            }),
        ];
    }
}
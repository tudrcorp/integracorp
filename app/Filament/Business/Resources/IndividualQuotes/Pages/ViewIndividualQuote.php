<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Pages;

use Filament\Actions\Action;
use App\Models\IndividualQuote;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;

class ViewIndividualQuote extends ViewRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('back')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->url(IndividualQuoteResource::getUrl('index')),
            Action::make('download')
                ->label('Descargar Cotización PDF')
                ->button()
                ->icon('heroicon-s-arrow-down-on-square-stack')
                ->color('success')
                ->action(function (IndividualQuote $record) {

                    try {

                        if (!file_exists(public_path('storage/quotes/' . $record->code . '.pdf'))) {

                            Notification::make()
                                ->title('NOTIFICACIÓN')
                                ->body('El documento asociado a la cotización no se encuentra disponible. Por favor, intente nuevamente en unos segundos.')
                                ->icon('heroicon-s-x-circle')
                                ->iconColor('warning')
                                ->warning()
                                ->send();

                            return;
                        }
                        /**
                         * Descargar el documento asociado a la cotizacion
                         * ruta: storage/
                         */
                        $path = public_path('storage/quotes/' . $record->code . '.pdf');
                        return response()->download($path);
                        
                    } catch (\Throwable $th) {
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
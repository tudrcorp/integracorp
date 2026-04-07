<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Pages;

use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Models\CorporateQuote;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateQuote extends ViewRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-s-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            Action::make('back')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ])
                ->url(CorporateQuoteResource::getUrl('index')),
            Action::make('download')
                ->label('Descargar Cotización PDF')
                ->button()
                ->icon('heroicon-s-arrow-down-on-square-stack')
                ->color('success')
                ->extraAttributes([
                    'class' => self::PRIMARY_BUTTON_CLASS,
                ])
                ->action(function (CorporateQuote $record) {

                    try {

                        if (! file_exists(public_path('storage/quotes/'.$record->code.'.pdf'))) {

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
                        $path = public_path('storage/quotes/'.$record->code.'.pdf');

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

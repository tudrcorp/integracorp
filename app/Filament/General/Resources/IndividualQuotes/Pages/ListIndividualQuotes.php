<?php

namespace App\Filament\General\Resources\IndividualQuotes\Pages;

use App\Filament\General\Resources\IndividualQuotes\IndividualQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndividualQuotes extends ListRecords
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Cotizaciones Individuales';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cotización individual')
                ->icon('heroicon-s-plus')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->color('success'),
        ];
    }
}

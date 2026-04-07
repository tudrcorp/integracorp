<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Pages;

use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateQuotes extends ListRecords
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Cotizaciones Corporativas';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear cotización corporativa')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}

<?php

namespace App\Filament\Operations\Resources\OperationTypeNegotiations\Pages;

use App\Filament\Operations\Resources\OperationTypeNegotiations\OperationTypeNegotiationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationTypeNegotiations extends ListRecords
{
    protected static string $resource = OperationTypeNegotiationResource::class;

    protected static ?string $title = 'Tipos de Negociación';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Tipo de Negociación')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}

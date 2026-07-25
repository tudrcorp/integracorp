<?php

namespace App\Filament\Operations\Resources\OperationInventoryUbications\Pages;

use App\Filament\Operations\Resources\OperationInventoryUbications\OperationInventoryUbicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventoryUbications extends ListRecords
{
    protected static string $resource = OperationInventoryUbicationResource::class;

    protected static ?string $title = 'Almacenes';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Almacén')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\PortalHelpContacts\Pages;

use App\Filament\Operations\Resources\PortalHelpContacts\PortalHelpContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPortalHelpContacts extends ListRecords
{
    protected static string $resource = PortalHelpContactResource::class;

    protected static ?string $title = 'Contactos de Ayuda Portal';

    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Contacto')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}

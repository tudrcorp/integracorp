<?php

namespace App\Filament\Marketing\Resources\Events\Pages;

use App\Filament\Marketing\Resources\Events\EventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Eventos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Evento')
                ->icon('heroicon-s-squares-plus')
        ];
    }
}
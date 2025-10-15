<?php

namespace App\Filament\Business\Resources\States\Pages;

use App\Filament\Business\Resources\States\StateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStates extends ListRecords
{
    protected static string $resource = StateResource::class;

    protected static ?string $title = 'Ubicaciones Geográficas: Estados';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear estado')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}
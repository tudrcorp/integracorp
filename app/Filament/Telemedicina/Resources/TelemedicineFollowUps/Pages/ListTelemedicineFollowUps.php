<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\TelemedicineFollowUpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineFollowUps extends ListRecords
{
    protected static string $resource = TelemedicineFollowUpResource::class;

    protected static ?string $title = 'Seguimiento de Casos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar Seguimiento')
                ->icon('heroicon-m-book-open')
                ->color('success'),
        ];
    }
}
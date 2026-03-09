<?php

namespace App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages;

use App\Filament\Marketing\Resources\CollaboratorAnniversaries\CollaboratorAnniversaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCollaboratorAnniversaries extends ListRecords
{
    protected static string $resource = CollaboratorAnniversaryResource::class;

    protected static ?string $title = 'Aniversario de Colaboradores';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo aniversario')
                ->icon('heroicon-o-cake')
        ];
    }
}

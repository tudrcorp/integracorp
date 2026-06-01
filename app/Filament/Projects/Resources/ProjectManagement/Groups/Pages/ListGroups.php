<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Groups\GroupResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected static ?string $title = 'Lista de Colaboradores y/o Grupos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo colaborador o grupo')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}

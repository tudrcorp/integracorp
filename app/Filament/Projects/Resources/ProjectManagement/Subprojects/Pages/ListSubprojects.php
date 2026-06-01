<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubprojects extends ListRecords
{
    protected static string $resource = SubprojectResource::class;

    protected static ?string $title = 'Lista de Subproyectos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo subproyecto')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}

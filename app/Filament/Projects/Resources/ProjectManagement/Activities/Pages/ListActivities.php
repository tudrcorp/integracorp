<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected static ?string $title = 'Lista de Actividades';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva actividad')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}

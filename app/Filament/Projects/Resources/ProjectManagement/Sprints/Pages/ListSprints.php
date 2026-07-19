<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Sprints\SprintResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSprints extends ListRecords
{
    protected static string $resource = SprintResource::class;

    protected static ?string $title = 'Lista de Sprints';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo sprint')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}

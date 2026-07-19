<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Epics\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Epics\EpicResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEpics extends ListRecords
{
    protected static string $resource = EpicResource::class;

    protected static ?string $title = 'Lista de Épicas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva épica')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}

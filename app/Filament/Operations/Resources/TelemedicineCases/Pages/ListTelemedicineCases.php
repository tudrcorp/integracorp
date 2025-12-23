<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\Pages;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineCases extends ListRecords
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Gestión de Casos';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
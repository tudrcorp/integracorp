<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineCases extends ListRecords
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
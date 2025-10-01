<?php

namespace App\Filament\Resources\TelemedicineCases\Pages;

use App\Filament\Resources\TelemedicineCases\TelemedicineCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListTelemedicineCases extends ListRecords
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Gestión de casos de telemedicína';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
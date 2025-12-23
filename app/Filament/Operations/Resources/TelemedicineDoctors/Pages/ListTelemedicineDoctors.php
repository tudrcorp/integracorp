<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Pages;

use App\Filament\Operations\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineDoctors extends ListRecords
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected static ?string $title = 'Doctores';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Nuevo Doctor')
                ->icon('heroicon-o-plus'),
        ];
    }
}
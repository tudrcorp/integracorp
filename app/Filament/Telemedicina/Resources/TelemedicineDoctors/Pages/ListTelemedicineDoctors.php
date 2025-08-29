<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;

class ListTelemedicineDoctors extends ListRecords
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected static ?string $title = 'Mi Perfil';

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         ViewAction::make()
    //             ->label('Ver')
    //             ->icon('heroicon-m-pencil')
    //             ->color('warning'),
    //     ];
    // }
}
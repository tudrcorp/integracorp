<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Pages;

use App\Filament\Operations\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineDoctor extends EditRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Deshabilitar Doctor')
                ->icon('heroicon-c-user-minus')
                ->modalIcon('heroicon-c-user-minus')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Deshabilitar Doctor')
                ->modalDescription('Estas seguro de deshabilitar a este doctor?')
                ->modalSubmitActionLabel('Deshabilitar')
                ->action(function () {
                    dd($this->getRecord());
                    $record = $this->getRecord();
                    $record->status = 'INACTIVO';
                    $record->save();
                })
        ];
    }
}
<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicinePatient extends EditRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TelemedicinePatientResource::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $name = trim((string) ($this->getRecord()?->full_name ?? ''));

        return Notification::make()
            ->success()
            ->icon('heroicon-o-check-circle')
            ->title('Paciente actualizado')
            ->body($name !== ''
                ? 'Se guardaron los cambios de '.$name.'. Volvió al listado de pacientes.'
                : 'Los cambios del paciente ya están guardados. Volvió al listado.');
    }
}

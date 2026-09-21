<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Support\Telemedicine\TelemedicineHistoryRelatedRecordsSync;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class CreateTelemedicineHistoryPatient extends CreateRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Formulario de Historia Clinica';

    protected function afterCreate(): void
    {
        TelemedicineHistoryRelatedRecordsSync::syncFromHistory(
            $this->getRecord(),
            Auth::user()?->name,
        );
    }

    protected function getRedirectUrl(): string
    {
        try {
            session()->put('redCode', false);

            return URL::route('filament.telemedicina.pages.dashboard');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al obtener el estado del formulario.')
                ->danger()
                ->send();

            Log::error('Error al obtener el estado del formulario: '.$e->getMessage());

            return $this->getResource()::getUrl('index');
        }
    }
}

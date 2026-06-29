<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditTelemedicineHistoryPatient extends EditRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Edición de historia clínica';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_view')
                ->label('Resumen Historia Clínica')
                ->icon(Heroicon::ArrowLeft)
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ])
                ->url(fn (): string => TelemedicineHistoryPatientResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        // ... Le digo a la varianle de sesion que maneja el formulario que lo muestre
        session()->put('redCode', false);

        $patient = session()->get('patient'); // $

        return route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
    }
}

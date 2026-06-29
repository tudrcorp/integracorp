<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTelemedicineHistoryPatient extends ViewRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Información de Paciente';

    protected function getHeaderActions(): array
    {
        return [

            EditAction::make()
                ->label('Editar Historia Clínica')
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),

            Action::make('back_to_consultations')
                ->label('Regresar a Consultas')
                ->icon(Heroicon::ArrowLeft)
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->action(function () {
                    $patient = session()->get('patient');

                    return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                }),

            Action::make('back')
                ->label('Dashboard')
                ->icon(Heroicon::Home)
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ])
                ->url(route('filament.telemedicina.pages.dashboard')),
        ];
    }
}

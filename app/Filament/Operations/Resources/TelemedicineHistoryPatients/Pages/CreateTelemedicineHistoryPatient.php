<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateTelemedicineHistoryPatient extends CreateRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Formulario para crear Historia Clínica';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl()),
        ];
    }

    /**
     * 
     * Metodo que se ejecuta antes de crear un registro
     * Valida que el RIF y el correo electrónico no se encuentren registrados en la base de datos.
     * 
     * @return void
     */
    protected function beforeCreate(): void
    {
        try {

            if (TelemedicineHistoryPatient::where('telemedicine_patient_id', $this->data['telemedicine_patient_id'])->exists()) {

                Notification::make()
                    ->title('ERROR')
                    ->body('El paciente ya se encuentra registrado en la tabla de historias clínicas.')
                    ->icon('heroicon-m-tag')
                    ->iconColor('danger')
                    ->danger()
                    ->send();

                Log::warning('El Usuario ' . Auth::user()->name . ' intento registrar una agencia con un correo electrónico ya existente.');

                $this->halt();
            }
        } catch (\Throwable $th) {
            Log::error('Error al registrar una historia clínica: ' . $th->getMessage());
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }
}

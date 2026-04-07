<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicineConsultationPatient;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineConsultationPatient extends ViewRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static ?string $title = 'Detalle de Seguimiento del Paciente';

    protected function getHeaderActions(): array
    {
        $actions = [];
        $record = $this->getRecord();

        if ($record instanceof TelemedicineConsultationPatient && $record->telemedicine_case_id) {
            $caseViewUrl = TelemedicineCaseResource::getUrl('view', [
                'record' => $record->telemedicine_case_id,
            ]).'?relation=consultations';
            if (request()->query('from') === 'patient') {
                $caseViewUrl .= '&from=patient';
            }
            $actions[] = Action::make('back_to_case_consultations')
                ->label('Volver a consultas del caso')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ])
                ->url($caseViewUrl);
        }

        if ($record instanceof TelemedicineConsultationPatient && $record->telemedicine_patient_id) {
            $actions[] = Action::make('back_to_patient')
                ->label('Volver al paciente')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ])
                ->url(TelemedicinePatientResource::getUrl('view', [
                    'record' => $record->telemedicine_patient_id,
                ]));
        }

        return $actions;
    }
}

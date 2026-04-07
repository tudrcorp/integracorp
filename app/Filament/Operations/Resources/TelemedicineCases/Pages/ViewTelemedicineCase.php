<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\Pages;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicineCase;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineCase extends ViewRecord
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Detalle de Caso';

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        $actions = [];

        $actions[] = Action::make('back_to_cases_list')
            ->label('Volver al listado de casos')
            ->icon('heroicon-o-arrow-left')
            ->color('gray')
            ->extraAttributes([
                'class' => self::TICKET_BUTTON_GRAY_CLASS,
            ])
            ->url(TelemedicineCaseResource::getUrl('index'));

        $case = $this->getRecord();
        $fromPatient = request()->query('from') === 'patient';

        if ($fromPatient && $case instanceof TelemedicineCase && $case->telemedicine_patient_id) {
            $actions[] = Action::make('back_to_patient')
                ->label('Volver al paciente')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ])
                ->url(TelemedicinePatientResource::getUrl('view', [
                    'record' => $case->telemedicine_patient_id,
                ]));
        }

        return $actions;
    }
}

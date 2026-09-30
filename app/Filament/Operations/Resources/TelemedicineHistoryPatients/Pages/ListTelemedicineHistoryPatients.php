<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Support\Operations\OperationsListHeaderCounts;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListTelemedicineHistoryPatients extends ListRecords
{
    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Historias Clínicas';

    public function getHeading(): string|Htmlable
    {
        $summary = OperationsListHeaderCounts::aggregate(
            $this->getTable()->getQuery(),
            [
                'month' => ['telemedicine_history_patients.created_at >= ?', [OperationsListHeaderCounts::startOfMonth()]],
                'today' => ['telemedicine_history_patients.created_at >= ?', [OperationsListHeaderCounts::startOfToday()]],
            ],
            ['patients' => 'telemedicine_history_patients.telemedicine_patient_id'],
        );

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-document-text',
            'eyebrow' => 'Telemedicina · Antecedentes',
            'title' => 'Historias clínicas',
            'total' => $summary['total'],
            'totalHint' => 'Historias clínicas que puede consultar',
            'description' => 'Antecedentes médicos de cada paciente. El código y el paciente abren el detalle; use los filtros para acotar por médico, fechas o antecedentes.',
            'stats' => [
                ['label' => 'Pacientes', 'value' => $summary['patients'], 'icon' => 'heroicon-m-users', 'tone' => 'info', 'hint' => 'Pacientes distintos con historia clínica'],
                ['label' => 'Este mes', 'value' => $summary['month'], 'icon' => 'heroicon-m-calendar-days', 'tone' => 'success', 'hint' => 'Historias creadas en el mes en curso'],
                ['label' => 'Hoy', 'value' => $summary['today'], 'icon' => 'heroicon-m-sparkles', 'tone' => 'primary', 'hint' => 'Historias creadas hoy'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Historia Clínica')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}

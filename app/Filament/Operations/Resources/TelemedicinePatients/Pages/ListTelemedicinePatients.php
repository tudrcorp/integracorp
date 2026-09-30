<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\ReportSiniestralidadAction;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\OperationsListHeaderCounts;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListTelemedicinePatients extends ListRecords
{
    /**
     * Misma apariencia que el botón "Crear Ticket" (menu-user): ticket-btn-ios en theme.css + píldora rounded-full.
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /** Misma forma iOS que TICKET_BUTTON_CLASS pero gris (theme.css .ticket-btn-ios-gray) */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Lista de Pacientes';

    public function getHeading(): string|Htmlable
    {
        $summary = OperationsListHeaderCounts::aggregate($this->getTable()->getQuery(), [
            'individual' => ['telemedicine_patients.type_affiliation = ?', ['INDIVIDUAL']],
            'corporate' => ['telemedicine_patients.type_affiliation = ?', ['CORPORATIVO']],
            'open_case' => [
                'EXISTS (SELECT 1 FROM telemedicine_cases AS tc WHERE tc.telemedicine_patient_id = telemedicine_patients.id AND tc.status NOT IN (?, ?))',
                ['ALTA MEDICA', 'ELIMINADO'],
            ],
            'month' => ['telemedicine_patients.created_at >= ?', [OperationsListHeaderCounts::startOfMonth()]],
        ]);

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-user-group',
            'eyebrow' => 'Telemedicina · Pacientes',
            'title' => 'Pacientes',
            'total' => $summary['total'],
            'totalHint' => 'Pacientes que puede consultar',
            'description' => 'Pacientes afiliados y externos de telemedicina. Muestre las columnas ocultas para ver domicilio y datos de afiliación.',
            'stats' => [
                ['label' => 'Con caso abierto', 'value' => $summary['open_case'], 'icon' => 'heroicon-m-clipboard-document-list', 'tone' => 'warning', 'hint' => 'Pacientes con al menos un caso sin alta médica'],
                ['label' => 'Individuales', 'value' => $summary['individual'], 'icon' => 'heroicon-m-user', 'tone' => 'info', 'hint' => 'Pacientes de afiliaciones individuales'],
                ['label' => 'Corporativos', 'value' => $summary['corporate'], 'icon' => 'heroicon-m-building-office-2', 'tone' => 'info', 'hint' => 'Pacientes de afiliaciones corporativas'],
                ['label' => 'Nuevos este mes', 'value' => $summary['month'], 'icon' => 'heroicon-m-sparkles', 'tone' => 'primary', 'hint' => 'Pacientes registrados en el mes en curso'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            ReportSiniestralidadAction::make(),
            CreateAction::make()
                ->label('Crear Nuevo Paciente')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->visible(fn (): bool => OperationsSupplierScope::currentSupplierId() === null),
        ];
    }
}

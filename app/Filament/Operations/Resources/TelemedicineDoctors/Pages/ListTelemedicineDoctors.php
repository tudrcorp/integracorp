<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Pages;

use App\Filament\Operations\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Support\Operations\OperationsListHeaderCounts;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListTelemedicineDoctors extends ListRecords
{
    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = TelemedicineDoctorResource::class;

    protected static ?string $title = 'Doctores';

    public function getHeading(): string|Htmlable
    {
        $summary = OperationsListHeaderCounts::aggregate($this->getTable()->getQuery(), [
            'active' => ['UPPER(telemedicine_doctors.status) IN (?, ?)', ['ACTIVO', 'ACTIVA']],
            'inactive' => ['UPPER(telemedicine_doctors.status) IN (?, ?)', ['INACTIVO', 'INACTIVA']],
            'without_signature' => ["(telemedicine_doctors.signature IS NULL OR TRIM(telemedicine_doctors.signature) = '')"],
            'tdg' => ['telemedicine_doctors.managed_by = ?', ['TDG']],
        ]);

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-user-circle',
            'eyebrow' => 'Telemedicina · Directorio médico',
            'title' => 'Doctores',
            'total' => $summary['total'],
            'totalHint' => 'Médicos que puede consultar',
            'description' => 'Contacto, identificación profesional, especialidad y sello digital de cada médico. Use «Editar» para actualizar la ficha o el sello.',
            'stats' => [
                ['label' => 'Activos', 'value' => $summary['active'], 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Médicos habilitados para atender'],
                ['label' => 'Inactivos', 'value' => $summary['inactive'], 'icon' => 'heroicon-m-no-symbol', 'tone' => 'danger', 'hint' => 'Médicos deshabilitados'],
                ['label' => 'Sin sello digital', 'value' => $summary['without_signature'], 'icon' => 'heroicon-m-exclamation-triangle', 'tone' => 'warning', 'hint' => 'Sus recetas e informes saldrían sin firma: cárguelo desde «Editar»'],
                ['label' => 'TDG', 'value' => $summary['tdg'], 'icon' => 'heroicon-m-building-office', 'tone' => 'info', 'hint' => 'Médicos gestionados por TDG; el resto pertenece a proveedores'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Nuevo Doctor')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}

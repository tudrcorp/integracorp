<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicineHistoryPatients extends ListRecords
{
    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Historias Clínicas';

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

<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineHistoryPatient extends ViewRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Detalle de la Historia Clínica';

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(static::getResource()::getUrl())
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ]),
            EditAction::make()
                ->label('Editar Historia Clínica')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),

        ];
    }
}

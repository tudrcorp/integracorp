<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Pages;

use App\Filament\Operations\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineDoctor extends EditRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Misma forma iOS que primary/gris; paleta roja tipo danger (theme.css .aviso-btn-ios-danger).
     */
    private const TICKET_BUTTON_DANGER_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Deshabilitar Doctor')
                ->icon('heroicon-c-user-minus')
                ->modalIcon('heroicon-c-user-minus')
                ->color('danger')
                ->extraAttributes(['class' => self::TICKET_BUTTON_DANGER_CLASS], merge: true)
                ->requiresConfirmation()
                ->modalHeading('Deshabilitar Doctor')
                ->modalDescription('Estas seguro de deshabilitar a este doctor?')
                ->modalSubmitActionLabel('Deshabilitar')
                ->modalSubmitAction(fn (Action $action) => $action->extraAttributes(['class' => self::TICKET_BUTTON_DANGER_CLASS], merge: true))
                ->action(function () {
                    $record = $this->getRecord();
                    $record->status = 'INACTIVO';
                    $record->save();
                }),
        ];
    }
}

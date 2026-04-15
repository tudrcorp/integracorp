<?php

namespace App\Filament\Operations\Resources\DoctorNurses\Pages;

use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use App\Models\DoctorNurse;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDoctorNurse extends EditRecord
{
    protected static string $resource = DoctorNurseResource::class;

    protected static ?string $title = 'Formulario de Edición del Proveedor Natural';

    // estilos de botones
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const TICKET_BUTTON_DANGER_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Proveedor natural actualizado')
            ->body('El proveedor natural ha sido actualizado exitosamente.');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver Proveedor Natural')
                ->url(fn (DoctorNurse $record) => $this->getResource()::getUrl('view', ['record' => $record]))
                ->color('gray')
                ->icon('heroicon-o-eye')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ]),
            DeleteAction::make()
                ->label('Eliminar Proveedor Natural')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Proveedor Natural')
                ->modalDescription('¿Está seguro de que desea eliminar este proveedor natural?')
                ->modalSubmitActionLabel('Eliminar')
                ->modalCancelActionLabel('Cancelar')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_DANGER_CLASS,
                ]),
        ];
    }
}

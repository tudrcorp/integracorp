<?php

namespace App\Filament\Operations\Resources\CorporateAllies\Pages;

use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use App\Models\CorporateAlly;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCorporateAlly extends EditRecord
{
    protected static string $resource = CorporateAllyResource::class;

    protected static ?string $title = 'Formulario de Edición de Aliado Corporativo';

    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

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
            ->title('Aliado corporativo actualizado')
            ->body('El aliado corporativo ha sido actualizado exitosamente.');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver aliado')
                ->url(fn (CorporateAlly $record) => $this->getResource()::getUrl('view', ['record' => $record]))
                ->color('gray')
                ->icon('heroicon-o-eye')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ]),
            DeleteAction::make()
                ->label('Eliminar aliado')
                ->requiresConfirmation()
                ->modalHeading('Eliminar aliado corporativo')
                ->modalDescription('¿Está seguro de que desea eliminar este aliado corporativo?')
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

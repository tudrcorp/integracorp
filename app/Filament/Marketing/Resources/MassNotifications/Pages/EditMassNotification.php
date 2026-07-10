<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Pages;

use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;
use App\Support\MassNotificationReschedule;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMassNotification extends EditRecord
{
    protected static string $resource = MassNotificationResource::class;

    protected bool $rescheduledForFutureSend = false;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->requiresConfirmation(fn (): bool => $this->shouldConfirmReschedule())
            ->modalHeading('Programar nuevo envío')
            ->modalDescription(fn (): string => MassNotificationReschedule::confirmationMessage(
                $this->data['date_programed'] ?? null,
            ))
            ->modalSubmitActionLabel('Programar reenvío');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->rescheduledForFutureSend = MassNotificationReschedule::shouldReschedule(
            $this->getRecord(),
            $data['date_programed'] ?? null,
        );

        return MassNotificationReschedule::applyRescheduleToFormData($this->getRecord(), $data);
    }

    protected function afterSave(): void
    {
        if (! $this->rescheduledForFutureSend) {
            return;
        }

        $scheduledAt = $this->getRecord()->date_programed?->format('d/m/Y H:i');

        Notification::make()
            ->title('Nuevo envío programado')
            ->body($scheduledAt !== null
                ? "La notificación se enviará automáticamente el {$scheduledAt}."
                : 'La notificación quedó lista para un nuevo envío programado.')
            ->success()
            ->send();
    }

    protected function shouldConfirmReschedule(): bool
    {
        return MassNotificationReschedule::shouldReschedule(
            $this->getRecord(),
            $this->data['date_programed'] ?? null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceOrder;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

final class OperationServiceOrderViewActions
{
    /**
     * @return list<string>
     */
    public static function closedStatuses(): array
    {
        return OperationServiceOrderValidity::closedStatuses();
    }

    public static function normalizedStatus(OperationServiceOrder $record): string
    {
        return mb_strtoupper(trim((string) ($record->status ?? '')));
    }

    public static function canCancel(OperationServiceOrder $record): bool
    {
        return ! in_array(self::normalizedStatus($record), self::closedStatuses(), true);
    }

    public static function canFinalize(OperationServiceOrder $record): bool
    {
        return self::canCancel($record);
    }

    public static function cancelOrder(OperationServiceOrder $record, mixed $livewire = null): void
    {
        if (! self::canCancel($record)) {
            Notification::make()
                ->warning()
                ->title('No se puede cancelar')
                ->body('Las órdenes finalizadas o ya canceladas no pueden cancelarse nuevamente.')
                ->send();

            return;
        }

        $record->update([
            'status' => 'CANCELADA',
            'updated_by' => Auth::user()?->name,
        ]);

        $freshRecord = $record->fresh() ?? $record;

        OperationServiceOrderCoordinationSync::cancelClinicalItemsForOrder($freshRecord);

        if (is_object($livewire) && property_exists($livewire, 'record')) {
            $livewire->record = $freshRecord;
        }

        Notification::make()
            ->success()
            ->title('Orden cancelada')
            ->body('La orden #'.($freshRecord->order_number ?: $freshRecord->getKey()).' quedó en estatus CANCELADA.')
            ->send();
    }

    public static function makeCancelAction(): Action
    {
        return Action::make('cancelServiceOrder')
            ->label('Cancelar orden de servicio')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->button()
            ->requiresConfirmation()
            ->modalHeading('Cancelar orden de servicio')
            ->modalDescription('Confirme que desea cancelar esta orden. No podrá revertirse desde aquí si la orden ya fue finalizada.')
            ->modalSubmitActionLabel('Sí, cancelar orden')
            ->modalCancelActionLabel('Volver')
            ->modalIcon(Heroicon::OutlinedXCircle)
            ->modalIconColor('danger')
            ->modalSubmitAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ])
            )
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
            ])
            ->visible(fn (OperationServiceOrder $record): bool => self::canCancel($record))
            ->action(function (OperationServiceOrder $record, mixed $livewire): void {
                self::cancelOrder($record, $livewire);
            });
    }
}

<?php

namespace App\Filament\Operations\Resources\FarmadocOrders\Actions;

use App\Jobs\RefreshFarmadocOrderStatuses;
use App\Models\FarmadocOrder;
use App\Support\Farmadoc\FarmadocException;
use App\Support\Farmadoc\FarmadocOrderTracker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class FarmadocOrderActions
{
    public static function refreshStatus(): Action
    {
        return Action::make('refreshFarmadocStatus')
            ->label('Actualizar estatus')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (FarmadocOrder $record): bool => $record->isOpen())
            ->action(function (FarmadocOrder $record): void {
                try {
                    FarmadocOrderTracker::refresh($record);
                } catch (FarmadocException $exception) {
                    Notification::make()
                        ->title('No se pudo consultar el pedido '.$record->order_number)
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Pedido '.$record->order_number.': '.FarmadocOrder::statusLabel($record->status))
                    ->success()
                    ->send();
            });
    }

    public static function refreshOpenStatuses(): Action
    {
        return Action::make('refreshOpenFarmadocStatuses')
            ->label('Actualizar pendientes')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => FarmadocOrder::query()->open()->exists())
            ->action(function (): void {
                RefreshFarmadocOrderStatuses::dispatch()->onQueue('system');

                Notification::make()
                    ->title('Consultando a Farmadoc')
                    ->body('Los estatus de los pedidos pendientes se actualizan en unos minutos.')
                    ->success()
                    ->send();
            });
    }
}

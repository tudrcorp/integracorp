<?php

namespace App\Filament\Operations\Resources\FarmadocOrders\Actions;

use App\Jobs\RefreshFarmadocOrderStatuses;
use App\Models\FarmadocOrder;
use App\Support\Farmadoc\FarmadocException;
use App\Support\Farmadoc\FarmadocReceiptNote;
use App\Support\Farmadoc\FarmadocOrderTracker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;

class FarmadocOrderActions
{
    public const DELIVERY_NOTE_ROUTE = 'operations.farmadoc-orders.delivery-note';

    public const RECEIPT_NOTE_ROUTE = 'operations.farmadoc-orders.receipt-note';

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

    /**
     * Abre la nota de entrega en una pestaña nueva (el visor del navegador permite imprimirla).
     */
    public static function viewDeliveryNote(): Action
    {
        return Action::make('viewFarmadocDeliveryNote')
            ->label('Nota de entrega')
            ->tooltip('Ver la nota de entrega que generó Farmadoc al aceptar el pedido')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('success')
            ->visible(fn (FarmadocOrder $record): bool => self::deliveryNoteAvailable($record))
            ->url(fn (FarmadocOrder $record): string => self::deliveryNoteUrl($record), shouldOpenInNewTab: true);
    }

    public static function downloadDeliveryNote(): Action
    {
        return Action::make('downloadFarmadocDeliveryNote')
            ->label('Descargar nota')
            ->tooltip('Descargar la nota de entrega en PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (FarmadocOrder $record): bool => self::deliveryNoteAvailable($record))
            ->url(fn (FarmadocOrder $record): string => self::deliveryNoteUrl($record, download: true));
    }

    /**
     * Las rutas están cacheadas en producción: hasta que se refresque la cache la acción
     * no se muestra, en vez de romper la tabla con una ruta inexistente.
     */
    public static function deliveryNoteAvailable(FarmadocOrder $record): bool
    {
        return $record->hasDeliveryNote() && Route::has(self::DELIVERY_NOTE_ROUTE);
    }

    public static function deliveryNoteUrl(FarmadocOrder $record, bool $download = false): string
    {
        return route(self::DELIVERY_NOTE_ROUTE, array_filter([
            'farmadocOrder' => $record->getKey(),
            'descargar' => $download ? 1 : null,
        ]));
    }

    /**
     * Nota de recibido y aprobado que genera IntegraCorp al dar entrada al pedido.
     */
    public static function viewReceiptNote(): Action
    {
        return Action::make('viewFarmadocReceiptNote')
            ->label('Nota de recibido')
            ->tooltip('Ver la nota de recibido y aprobado de IntegraCorp')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('success')
            ->visible(fn (FarmadocOrder $record): bool => self::receiptNoteAvailable($record))
            ->url(fn (FarmadocOrder $record): string => self::receiptNoteUrl($record), shouldOpenInNewTab: true);
    }

    public static function receiptNoteAvailable(FarmadocOrder $record): bool
    {
        return FarmadocReceiptNote::available($record) && Route::has(self::RECEIPT_NOTE_ROUTE);
    }

    public static function receiptNoteUrl(FarmadocOrder $record, bool $download = false): string
    {
        return route(self::RECEIPT_NOTE_ROUTE, array_filter([
            'farmadocOrder' => $record->getKey(),
            'descargar' => $download ? 1 : null,
        ]));
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

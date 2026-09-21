<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Pages;

use App\Filament\Operations\Resources\OperationAccountsPayables\Actions\AccountsPayablePaymentReceiptActions;
use App\Filament\Operations\Resources\OperationAccountsPayables\OperationAccountsPayableResource;
use App\Models\OperationAccountsPayable;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOperationAccountsPayable extends EditRecord
{
    protected static string $resource = OperationAccountsPayableResource::class;

    protected static ?string $title = 'Editar factura por pagar';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Factura actualizada')
            ->body('Los cambios de la factura se guardaron correctamente.');
    }

    protected function getHeaderActions(): array
    {
        return [
            AccountsPayablePaymentReceiptActions::makeRecordAction(),
            ViewAction::make()
                ->label('Ver factura')
                ->url(fn (OperationAccountsPayable $record): string => $this->getResource()::getUrl('view', ['record' => $record]))
                ->color('gray')
                ->icon('heroicon-o-eye'),
            DeleteAction::make()
                ->label('Eliminar factura')
                ->requiresConfirmation()
                ->modalHeading('Eliminar factura')
                ->modalDescription('Se eliminará la factura y los datos de pago asociados. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Eliminar')
                ->modalCancelActionLabel('Cancelar')
                ->color('danger')
                ->icon('heroicon-o-trash'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Pages;

use App\Filament\Operations\Resources\OperationAccountsPayables\OperationAccountsPayableResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationAccountsPayable extends CreateRecord
{
    protected static string $resource = OperationAccountsPayableResource::class;

    protected static ?string $title = 'Registrar factura por pagar';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Factura registrada')
            ->body('La factura quedó registrada en cuentas por pagar.');
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Pages;

use App\Filament\Operations\Resources\OperationAccountsPayables\OperationAccountsPayableResource;
use App\Support\Operations\AccountsPayableInvoicePreview;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationAccountsPayable extends ViewRecord
{
    protected static string $resource = OperationAccountsPayableResource::class;

    protected static ?string $title = 'Detalle de la factura';

    protected function getHeaderActions(): array
    {
        return [
            AccountsPayableInvoicePreview::action(),
            EditAction::make()
                ->label('Editar factura')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}

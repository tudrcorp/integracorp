<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Pages;

use App\Filament\Operations\Resources\OperationAccountsPayables\OperationAccountsPayableResource;
use Filament\Resources\Pages\ListRecords;

class ListOperationAccountsPayables extends ListRecords
{
    protected static string $resource = OperationAccountsPayableResource::class;

    protected static ?string $title = 'Cuentas por pagar';

    protected function getHeaderActions(): array
    {
        return [];
    }
}

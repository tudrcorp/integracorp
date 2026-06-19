<?php

namespace App\Filament\Operations\Resources\AccountsPayables\Pages;

use App\Filament\Operations\Resources\AccountsPayables\AccountsPayableResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountsPayables extends ListRecords
{
    protected static string $resource = AccountsPayableResource::class;

    protected static ?string $title = 'Cuentas por pagar';
}

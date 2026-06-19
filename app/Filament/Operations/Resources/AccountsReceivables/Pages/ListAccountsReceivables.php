<?php

namespace App\Filament\Operations\Resources\AccountsReceivables\Pages;

use App\Filament\Operations\Resources\AccountsReceivables\AccountsReceivableResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountsReceivables extends ListRecords
{
    protected static string $resource = AccountsReceivableResource::class;

    protected static ?string $title = 'Cuentas por cobrar';
}

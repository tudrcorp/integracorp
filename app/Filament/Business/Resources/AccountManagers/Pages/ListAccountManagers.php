<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountManagers extends ListRecords
{
    protected static string $resource = AccountManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountManager extends EditRecord
{
    protected static string $resource = AccountManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountManager extends CreateRecord
{
    protected static string $resource = AccountManagerResource::class;
}

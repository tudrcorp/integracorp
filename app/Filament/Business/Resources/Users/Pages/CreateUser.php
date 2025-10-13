<?php

namespace App\Filament\Business\Resources\Users\Pages;

use App\Filament\Business\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}

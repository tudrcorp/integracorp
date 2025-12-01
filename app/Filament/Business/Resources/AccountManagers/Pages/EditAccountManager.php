<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;

class EditAccountManager extends EditRecord
{
    protected static string $resource = AccountManagerResource::class;

    protected static ?string $title = 'Editar Account Manager';

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }
}
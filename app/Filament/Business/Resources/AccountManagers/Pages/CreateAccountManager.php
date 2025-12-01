<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Filament\Resources\Pages\CreateRecord;
use Symfony\Component\String\TruncateMode;
use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;

class CreateAccountManager extends CreateRecord
{
    protected static string $resource = AccountManagerResource::class;

    protected static ?string $title = 'Formulario para Creación de Account Manager';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {

        $user = new User();
        $user->name = $this->getRecord()->full_name;
        $user->phone = $this->getRecord()->phone;
        $user->email = $this->getRecord()->email;
        $user->password = Hash::make(12345678);
        $user->is_accountManagers = true;
        $user->departament = 'NEGOCIOS';
        $user->status = 'ACTIVO';
        $user->save();

        $this->getRecord()->user_id = $user->id;
        $this->getRecord()->save();
        
    }
}
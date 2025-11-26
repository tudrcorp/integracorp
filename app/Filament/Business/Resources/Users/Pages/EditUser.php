<?php

namespace App\Filament\Business\Resources\Users\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Business\Resources\Users\UserResource;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Editar Usuario';

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;
        
        return $data;
    }
}
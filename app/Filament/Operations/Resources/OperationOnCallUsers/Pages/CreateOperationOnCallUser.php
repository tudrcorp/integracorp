<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Pages;

use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOperationOnCallUser extends CreateRecord
{
    protected static string $resource = OperationOnCallUserResource::class;

    protected static ?string $title = 'Asignar Colaborador de Guardia';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userName = Auth::user()?->name ?? '—';
        $data['created_by'] = $userName;
        $data['updated_by'] = $userName;

        return $data;
    }
}

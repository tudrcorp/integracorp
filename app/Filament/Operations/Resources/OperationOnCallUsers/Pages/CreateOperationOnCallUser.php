<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Pages;

use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationOnCallUser extends CreateRecord
{
    protected static string $resource = OperationOnCallUserResource::class;

    protected static ?string $title = 'Asignar Colaborador de Guardia';
}

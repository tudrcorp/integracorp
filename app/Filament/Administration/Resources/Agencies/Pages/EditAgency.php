<?php

namespace App\Filament\Administration\Resources\Agencies\Pages;

use App\Filament\Administration\Resources\Agencies\AgencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Editar Informacion de la Agencias';


}

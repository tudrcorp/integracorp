<?php

namespace App\Filament\Marketing\Resources\CollaboratorAnniversaries\Pages;

use App\Filament\Marketing\Resources\CollaboratorAnniversaries\CollaboratorAnniversaryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCollaboratorAnniversary extends CreateRecord
{
    protected static string $resource = CollaboratorAnniversaryResource::class;

    protected static ?string $title = 'Nueva Tarjeta de Aniversario';
}

<?php

namespace App\Filament\Resources\CheckAffiliations\Pages;

use App\Filament\Resources\CheckAffiliations\CheckAffiliationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCheckAffiliation extends CreateRecord
{
    protected static string $resource = CheckAffiliationResource::class;

    protected static ?string $title = 'Histórico de Afiliaciones';
}
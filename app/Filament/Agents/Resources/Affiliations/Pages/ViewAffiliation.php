<?php

namespace App\Filament\Agents\Resources\Affiliations\Pages;

use App\Filament\Agents\Resources\Affiliations\AffiliationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliation extends ViewRecord
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Información General';

}
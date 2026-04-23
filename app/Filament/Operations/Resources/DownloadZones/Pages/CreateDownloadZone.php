<?php

namespace App\Filament\Operations\Resources\DownloadZones\Pages;

use App\Filament\Operations\Resources\DownloadZones\DownloadZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDownloadZone extends CreateRecord
{
    protected static string $resource = DownloadZoneResource::class;
}

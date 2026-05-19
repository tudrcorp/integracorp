<?php

namespace App\Filament\Operations\Resources\DownloadZones\Pages;

use App\Filament\Operations\Resources\DownloadZones\DownloadZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDownloadZone extends CreateRecord
{
    protected static string $resource = DownloadZoneResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

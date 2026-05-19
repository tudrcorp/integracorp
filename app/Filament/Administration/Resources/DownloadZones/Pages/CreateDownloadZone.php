<?php

namespace App\Filament\Administration\Resources\DownloadZones\Pages;

use App\Filament\Administration\Resources\DownloadZones\DownloadZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDownloadZone extends CreateRecord
{
    protected static string $resource = DownloadZoneResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Master\Resources\DownloadZones\Pages;

use App\Filament\Master\Resources\DownloadZones\DownloadZoneResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDownloadZone extends ViewRecord
{
    protected static string $resource = DownloadZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),
        ];
    }
}
<?php

namespace App\Filament\Master\Resources\DownloadZones\Pages;

use App\Filament\Master\Resources\DownloadZones\DownloadZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

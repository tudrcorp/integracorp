<?php

namespace App\Filament\Agents\Resources\DownloadZones\Pages;

use App\Filament\Agents\Resources\DownloadZones\DownloadZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'ZONA DE DESCARGA';


    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
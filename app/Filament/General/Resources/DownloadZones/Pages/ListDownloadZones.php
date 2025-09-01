<?php

namespace App\Filament\General\Resources\DownloadZones\Pages;

use App\Filament\General\Resources\DownloadZones\DownloadZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDownloadZones extends ListRecords
{
    protected static string $resource = DownloadZoneResource::class;

    protected static ?string $title = 'Zona de Descargas';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
<?php

namespace App\Filament\Business\Resources\DownloadZones\Pages;

use App\Filament\Business\Resources\DownloadZones\DownloadZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDownloadZone extends EditRecord
{
    protected static string $resource = DownloadZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

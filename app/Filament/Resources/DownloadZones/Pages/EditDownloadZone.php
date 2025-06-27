<?php

namespace App\Filament\Resources\DownloadZones\Pages;

use App\Filament\Resources\DownloadZones\DownloadZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDownloadZone extends EditRecord
{
    protected static string $resource = DownloadZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

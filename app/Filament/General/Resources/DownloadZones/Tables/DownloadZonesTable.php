<?php

namespace App\Filament\General\Resources\DownloadZones\Tables;

use App\Models\DownloadZone;
use App\Support\DownloadZoneDocumentDownloader;
use App\Support\Filament\DownloadZonesBrowsableTable;
use Filament\Tables\Table;

class DownloadZonesTable
{
    public static function configure(Table $table): Table
    {
        return DownloadZonesBrowsableTable::configure(
            $table,
            fn ($action) => $action
                ->action(fn (DownloadZone $record): ?\Symfony\Component\HttpFoundation\StreamedResponse => DownloadZoneDocumentDownloader::download(
                    $record,
                    'general',
                )),
        );
    }
}

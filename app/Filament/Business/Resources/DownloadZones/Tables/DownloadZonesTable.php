<?php

namespace App\Filament\Business\Resources\DownloadZones\Tables;

use App\Support\Filament\DownloadZonesBrowsableTable;
use Filament\Tables\Table;

class DownloadZonesTable
{
    public static function configure(Table $table): Table
    {
        return DownloadZonesBrowsableTable::configure(
            $table,
            fn ($action) => $action
                ->url(fn ($record) => asset('storage/'.$record->document))
                ->openUrlInNewTab(),
        );
    }
}

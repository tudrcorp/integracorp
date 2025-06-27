<?php

namespace App\Filament\Agents\Resources\DownloadZones\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DownloadZoneInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('zone_id')
                    ->numeric(),
                TextEntry::make('document'),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                ImageEntry::make('image_icon'),
                TextEntry::make('description'),
            ]);
    }
}

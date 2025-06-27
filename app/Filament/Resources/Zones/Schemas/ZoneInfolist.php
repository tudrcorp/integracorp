<?php

namespace App\Filament\Resources\Zones\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ZoneInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('zone'),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('created_by'),
            ]);
    }
}

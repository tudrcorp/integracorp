<?php

namespace App\Filament\Resources\BusinessUnits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BusinessUnitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('definition'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

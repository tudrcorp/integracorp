<?php

namespace App\Filament\Resources\TelemedicineStudiesLists\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TelemedicineStudiesListInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('category'),
                TextEntry::make('description'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

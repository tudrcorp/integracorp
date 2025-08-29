<?php

namespace App\Filament\Resources\Guests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GuestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('event_id'),
                TextEntry::make('fullName'),
                TextEntry::make('phone'),
                TextEntry::make('agency'),
                TextEntry::make('companion'),
                TextEntry::make('webBrowser'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

<?php

namespace App\Filament\Marketing\Resources\InfoFrees\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InfoFreeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('fullName'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('phone'),
                TextEntry::make('sex'),
                TextEntry::make('address'),
                TextEntry::make('city'),
                TextEntry::make('country'),
                TextEntry::make('state'),
                TextEntry::make('region'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

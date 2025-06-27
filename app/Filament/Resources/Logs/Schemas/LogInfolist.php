<?php

namespace App\Filament\Resources\Logs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('action'),
                TextEntry::make('method'),
                TextEntry::make('route'),
                TextEntry::make('ip'),
                TextEntry::make('user_agent'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

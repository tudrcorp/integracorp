<?php

namespace App\Filament\Operations\Resources\OperationInventories\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OperationInventoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('unit'),
                TextEntry::make('type'),
                TextEntry::make('existence')
                    ->numeric(),
                TextEntry::make('cost')
                    ->money(),
                TextEntry::make('created_by'),
                TextEntry::make('updated_by')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

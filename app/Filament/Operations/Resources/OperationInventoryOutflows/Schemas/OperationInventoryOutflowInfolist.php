<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OperationInventoryOutflowInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('operation_inventory_id')
                    ->numeric(),
                TextEntry::make('quantity')
                    ->numeric(),
                TextEntry::make('unit'),
                TextEntry::make('type'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Fees\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('ageRange.id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('price')
                    ->money(),
                TextEntry::make('status'),
                TextEntry::make('range'),
                TextEntry::make('coverage'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

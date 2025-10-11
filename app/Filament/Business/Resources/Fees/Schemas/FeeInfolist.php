<?php

namespace App\Filament\Business\Resources\Fees\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')
                    ->placeholder('-'),
                TextEntry::make('ageRange.id')
                    ->label('Age range'),
                TextEntry::make('coverage_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('price')
                    ->money(),
                TextEntry::make('status'),
                TextEntry::make('range')
                    ->placeholder('-'),
                TextEntry::make('coverage')
                    ->placeholder('-'),
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

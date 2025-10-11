<?php

namespace App\Filament\Business\Resources\AgeRanges\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AgeRangeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('plan.id')
                    ->label('Plan'),
                TextEntry::make('coverage_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('fee')
                    ->placeholder('-'),
                TextEntry::make('code'),
                TextEntry::make('range'),
                TextEntry::make('status')
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('age_init')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('age_end')
                    ->numeric()
                    ->placeholder('-'),
            ]);
    }
}

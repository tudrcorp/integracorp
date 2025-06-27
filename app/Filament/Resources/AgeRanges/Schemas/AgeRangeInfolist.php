<?php

namespace App\Filament\Resources\AgeRanges\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AgeRangeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('plan.id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('fee'),
                TextEntry::make('code'),
                TextEntry::make('range'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('age_init')
                    ->numeric(),
                TextEntry::make('age_end')
                    ->numeric(),
            ]);
    }
}

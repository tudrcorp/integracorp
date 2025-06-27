<?php

namespace App\Filament\Resources\Coverages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CoverageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('plan.id')
                    ->numeric(),
                TextEntry::make('code'),
                TextEntry::make('price')
                    ->money(),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Benefits\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BenefitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('limit.id')
                    ->numeric(),
                TextEntry::make('code'),
                TextEntry::make('description'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('price')
                    ->money(),
            ]);
    }
}

<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DressTylorQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('subtitle')
                    ->placeholder('-'),
                TextEntry::make('agent_id')
                    ->placeholder('-'),
                TextEntry::make('agency_code')
                    ->placeholder('-'),
                TextEntry::make('owner_code')
                    ->placeholder('-'),
                TextEntry::make('total'),
                TextEntry::make('anual'),
                TextEntry::make('mensual'),
                TextEntry::make('trimestral'),
                TextEntry::make('semestral'),
                TextEntry::make('status'),
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

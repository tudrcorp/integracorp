<?php

namespace App\Filament\Resources\IndividualQuotes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IndividualQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('owner_code'),
                TextEntry::make('code_agency'),
                TextEntry::make('code'),
                TextEntry::make('code_agent'),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('state_id')
                    ->numeric(),
                TextEntry::make('count_days')
                    ->numeric(),
                TextEntry::make('region'),
                TextEntry::make('full_name'),
                TextEntry::make('birth_date'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('owner_agent'),
                TextEntry::make('plan'),
                TextEntry::make('type'),
            ]);
    }
}

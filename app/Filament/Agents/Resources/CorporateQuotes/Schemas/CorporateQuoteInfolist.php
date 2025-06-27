<?php

namespace App\Filament\Agents\Resources\CorporateQuotes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CorporateQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('owner_code'),
                TextEntry::make('code'),
                TextEntry::make('code_agency'),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('state_id')
                    ->numeric(),
                TextEntry::make('region'),
                TextEntry::make('count_days')
                    ->numeric(),
                TextEntry::make('full_name'),
                TextEntry::make('rif'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('corporate_quote_request_id')
                    ->numeric(),
                TextEntry::make('plan'),
            ]);
    }
}

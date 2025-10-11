<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CorporateQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('corporate_quote_request_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('owner_code')
                    ->placeholder('-'),
                TextEntry::make('code'),
                TextEntry::make('code_agency')
                    ->placeholder('-'),
                TextEntry::make('agent_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('state_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('region')
                    ->placeholder('-'),
                TextEntry::make('count_days')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('full_name')
                    ->placeholder('-'),
                TextEntry::make('rif')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->placeholder('-'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('plan')
                    ->placeholder('-'),
                TextEntry::make('observations')
                    ->placeholder('-'),
                TextEntry::make('data_doc')
                    ->placeholder('-'),
                TextEntry::make('observation_dress_tailor')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('type')
                    ->placeholder('-'),
            ]);
    }
}

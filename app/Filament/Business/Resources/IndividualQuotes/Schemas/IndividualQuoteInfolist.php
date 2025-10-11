<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IndividualQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('owner_code')
                    ->placeholder('-'),
                TextEntry::make('code_agency')
                    ->placeholder('-'),
                TextEntry::make('code'),
                TextEntry::make('agent.name')
                    ->label('Agent')
                    ->placeholder('-'),
                TextEntry::make('state.id')
                    ->label('State')
                    ->placeholder('-'),
                TextEntry::make('count_days')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('region')
                    ->placeholder('-'),
                TextEntry::make('full_name')
                    ->placeholder('-'),
                TextEntry::make('birth_date')
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
                TextEntry::make('owner_agent')
                    ->placeholder('-'),
                TextEntry::make('plan')
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->placeholder('-'),
                IconEntry::make('assignment_status')
                    ->boolean(),
                TextEntry::make('age')
                    ->numeric()
                    ->placeholder('-'),
            ]);
    }
}

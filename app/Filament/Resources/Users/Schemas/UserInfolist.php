<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('email_verified_at')
                    ->dateTime(),
                TextEntry::make('code_agency'),
                TextEntry::make('agency_type'),
                IconEntry::make('is_admin')
                    ->boolean(),
                IconEntry::make('is_agent')
                    ->boolean(),
                IconEntry::make('is_subagent')
                    ->boolean(),
                IconEntry::make('is_agency')
                    ->boolean(),
                TextEntry::make('code_agent'),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('phone'),
            ]);
    }
}

<?php

namespace App\Filament\General\Resources\CorporateQuoteRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CorporateQuoteRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('code_agent'),
                TextEntry::make('owner_code'),
                TextEntry::make('agent_id')
                    ->numeric(),
                TextEntry::make('code_agency'),
                TextEntry::make('full_name'),
                TextEntry::make('rif'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('state_id')
                    ->numeric(),
                TextEntry::make('region'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('document'),
                TextEntry::make('observations'),
                TextEntry::make('owner_application'),
            ]);
    }
}

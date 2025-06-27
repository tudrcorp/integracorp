<?php

namespace App\Filament\Resources\Collaborators\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CollaboratorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('full_name'),
                TextEntry::make('dni'),
                TextEntry::make('birth_date'),
                TextEntry::make('company_init_date'),
                TextEntry::make('departament'),
                TextEntry::make('position'),
                TextEntry::make('sex'),
                TextEntry::make('phone'),
                TextEntry::make('coorporate_email'),
                TextEntry::make('alternative_email'),
                TextEntry::make('status'),
                TextEntry::make('created_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}

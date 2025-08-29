<?php

namespace App\Filament\Resources\Guests\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event_id')
                    ->required(),
                TextInput::make('fullName')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('agency')
                    ->required(),
                TextInput::make('companion')
                    ->required(),
                TextInput::make('webBrowser')
                    ->required(),
            ]);
    }
}

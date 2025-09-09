<?php

namespace App\Filament\Marketing\Resources\InfoFrees\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InfoFreeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('fullName')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('sex')
                    ->required(),
                TextInput::make('address')
                    ->required(),
                TextInput::make('city')
                    ->required(),
                TextInput::make('country')
                    ->required(),
                TextInput::make('state')
                    ->required(),
                TextInput::make('region')
                    ->required(),
            ]);
    }
}

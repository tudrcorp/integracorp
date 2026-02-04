<?php

namespace App\Filament\Business\Resources\TravelAgents\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TravelAgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('cargo')
                    ->required(),
                TextInput::make('fechaNacimiento')
                    ->required(),
                TextInput::make('createdBy')
                    ->required(),
                TextInput::make('updatedBy')
                    ->required(),
                TextInput::make('travel_agency_id')
                    ->required()
                    ->numeric(),
            ]);
    }
}

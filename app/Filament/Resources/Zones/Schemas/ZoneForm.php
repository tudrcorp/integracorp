<?php

namespace App\Filament\Resources\Zones\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('zone')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVA'),
                TextInput::make('created_by'),
            ]);
    }
}

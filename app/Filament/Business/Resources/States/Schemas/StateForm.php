<?php

namespace App\Filament\Business\Resources\States\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country_id')
                    ->numeric(),
                TextInput::make('definition')
                    ->required(),
                TextInput::make('region_id')
                    ->numeric(),
            ]);
    }
}

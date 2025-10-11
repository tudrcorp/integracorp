<?php

namespace App\Filament\Business\Resources\Regions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RegionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country_id')
                    ->numeric(),
                TextInput::make('definition')
                    ->required(),
            ]);
    }
}

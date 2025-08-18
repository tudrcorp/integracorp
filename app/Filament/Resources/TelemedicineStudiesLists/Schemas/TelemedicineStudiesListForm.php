<?php

namespace App\Filament\Resources\TelemedicineStudiesLists\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelemedicineStudiesListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('category')
                    ->required(),
                TextInput::make('description')
                    ->required(),
            ]);
    }
}

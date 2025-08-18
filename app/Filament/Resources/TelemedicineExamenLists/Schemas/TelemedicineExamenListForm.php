<?php

namespace App\Filament\Resources\TelemedicineExamenLists\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelemedicineExamenListForm
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

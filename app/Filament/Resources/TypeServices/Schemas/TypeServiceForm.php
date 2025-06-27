<?php

namespace App\Filament\Resources\TypeServices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TypeServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('definition')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                TextInput::make('created_by')
                    ->required(),
            ]);
    }
}

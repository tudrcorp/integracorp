<?php

namespace App\Filament\Resources\Responsibles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ResponsibleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('full_name')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                TextInput::make('created_by')
                    ->required(),
            ]);
    }
}

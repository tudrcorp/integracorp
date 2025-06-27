<?php

namespace App\Filament\Resources\Takers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TakerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name'),
                TextInput::make('type_document'),
                TextInput::make('number_document'),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                TextInput::make('created_by'),
            ]);
    }
}

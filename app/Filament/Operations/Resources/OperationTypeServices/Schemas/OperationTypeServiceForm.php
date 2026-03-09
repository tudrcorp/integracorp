<?php

namespace App\Filament\Operations\Resources\OperationTypeServices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OperationTypeServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                TextInput::make('created_by'),
                TextInput::make('updated_by'),
            ]);
    }
}

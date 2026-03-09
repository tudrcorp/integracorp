<?php

namespace App\Filament\Operations\Resources\OperationStatusServices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OperationStatusServiceForm
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

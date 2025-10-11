<?php

namespace App\Filament\Business\Resources\BusinessLines\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BusinessLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('business_unit_id')
                    ->required()
                    ->numeric()
                    ->default(0),
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

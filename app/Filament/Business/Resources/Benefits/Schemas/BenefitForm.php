<?php

namespace App\Filament\Business\Resources\Benefits\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BenefitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('limit_id')
                    ->relationship('limit', 'id'),
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                TextInput::make('created_by')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
            ]);
    }
}

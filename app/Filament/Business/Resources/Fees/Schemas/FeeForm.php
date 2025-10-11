<?php

namespace App\Filament\Business\Resources\Fees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code'),
                Select::make('age_range_id')
                    ->relationship('ageRange', 'id')
                    ->required(),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                TextInput::make('range'),
                TextInput::make('coverage'),
                TextInput::make('created_by')
                    ->required(),
            ]);
    }
}

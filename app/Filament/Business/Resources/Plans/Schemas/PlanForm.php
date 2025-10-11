<?php

namespace App\Filament\Business\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('business_unit_id')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                TextInput::make('created_by')
                    ->required(),
                TextInput::make('type')
                    ->default('BASICO'),
            ]);
    }
}

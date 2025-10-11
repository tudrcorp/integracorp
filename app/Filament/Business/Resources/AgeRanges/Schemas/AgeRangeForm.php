<?php

namespace App\Filament\Business\Resources\AgeRanges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AgeRangeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plan_id')
                    ->relationship('plan', 'id')
                    ->required()
                    ->default(0),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('fee'),
                TextInput::make('code')
                    ->required(),
                TextInput::make('range')
                    ->required(),
                TextInput::make('status'),
                TextInput::make('created_by'),
                TextInput::make('age_init')
                    ->numeric(),
                TextInput::make('age_end')
                    ->numeric(),
            ]);
    }
}

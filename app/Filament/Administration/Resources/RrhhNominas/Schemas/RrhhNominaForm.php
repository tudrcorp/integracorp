<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RrhhNominaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('total_salarios')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_descuentos')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_asignaciones')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('total_neto')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}

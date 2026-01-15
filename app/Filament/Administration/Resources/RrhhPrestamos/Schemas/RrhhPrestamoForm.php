<?php

namespace App\Filament\Administration\Resources\RrhhPrestamos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RrhhPrestamoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('colaborador_id')
                    ->required()
                    ->numeric(),
                TextInput::make('descripcion')
                    ->required(),
                TextInput::make('monto')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('nro_cuotas')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required(),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}

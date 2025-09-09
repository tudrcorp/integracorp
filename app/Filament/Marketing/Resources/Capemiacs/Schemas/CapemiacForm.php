<?php

namespace App\Filament\Marketing\Resources\Capemiacs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CapemiacForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('cliente')
                    ->required(),
                TextInput::make('segmento')
                    ->required(),
                TextInput::make('rif')
                    ->required(),
                TextInput::make('telefonoUno')
                    ->tel()
                    ->required(),
                TextInput::make('telefonoDos')
                    ->tel()
                    ->required(),
                TextInput::make('telefonoTres')
                    ->tel()
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('fecha_registro')
                    ->required(),
            ]);
    }
}

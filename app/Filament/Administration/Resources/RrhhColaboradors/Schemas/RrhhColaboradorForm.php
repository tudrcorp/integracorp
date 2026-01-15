<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RrhhColaboradorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('fullName'),
                TextInput::make('departmento_id'),
                TextInput::make('cargo_id'),
                TextInput::make('cedula'),
                TextInput::make('sexo'),
                TextInput::make('fechaNacimiento'),
                TextInput::make('fechaIngreso'),
                TextInput::make('telefono')
                    ->tel(),
                TextInput::make('telefonoCorporativo')
                    ->tel(),
                TextInput::make('emailCorporativo')
                    ->email(),
                TextInput::make('emailAlternativo')
                    ->email(),
                TextInput::make('emailPersonal')
                    ->email(),
                TextInput::make('direccion'),
                TextInput::make('nroHijos'),
                TextInput::make('nroHijoDependiente'),
                TextInput::make('tallaCamisa'),
                TextInput::make('banck_id'),
                TextInput::make('nroCta'),
                TextInput::make('codigoCta'),
                TextInput::make('tipoCta'),
                TextInput::make('status')
                    ->required()
                    ->default('activo'),
                TextInput::make('created_by'),
                TextInput::make('updated_by'),
            ]);
    }
}

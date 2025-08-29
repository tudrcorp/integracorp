<?php

namespace App\Filament\Resources\CheckAgentAgencies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CheckAgentAgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('codificacion_agente')
                    ->required(),
                TextInput::make('codigo_agente')
                    ->required(),
                TextInput::make('nombre_agencia_agente')
                    ->required(),
                TextInput::make('nombre_representante')
                    ->required(),
                TextInput::make('nro_identificacion')
                    ->required(),
                TextInput::make('fecha_nacimiento')
                    ->required(),
                TextInput::make('fecha_ingreso')
                    ->required(),
                TextInput::make('estatus')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('telefono')
                    ->tel()
                    ->required(),
                TextInput::make('usuario_instagram')
                    ->required(),
                TextInput::make('pais')
                    ->required(),
                TextInput::make('estado')
                    ->required(),
                TextInput::make('ciudad')
                    ->required(),
                TextInput::make('tdec')
                    ->required(),
                TextInput::make('tdev')
                    ->required(),
                TextInput::make('tipo_agente')
                    ->required(),
                TextInput::make('agente_supervisor')
                    ->required(),
                TextInput::make('agencia_master')
                    ->required(),
            ]);
    }
}

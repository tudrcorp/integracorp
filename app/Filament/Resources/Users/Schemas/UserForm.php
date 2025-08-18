<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Forms\Components\DateTimePicker;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información Principal')
                        ->schema([
                            TextInput::make('name')
                                ->Label('Nombre y Apellido')
                                ->required(),
                            TextInput::make('phone')
                                ->label('Número de Teléfono')
                                ->tel(),
                            TextInput::make('email')
                                ->label('Correo Electrónico')
                                ->email()
                                ->unique('users', 'email')
                                ->live()
                                ->required()
                                ->validationMessages([
                                    'required'  => 'Campo Requerido',
                                    'unique'    => 'El correo electrónico ya existe',
                                    'email'     => 'Formato incorrecto',
                                ]),
                            TextInput::make('status')
                                ->default('ACTIVO'),
                        ])->columns(4),
                    Step::make('Contraseña')
                        ->schema([
                            TextInput::make('password')
                                ->label('Contraseña')
                                ->helperText('La contraseña debe contener al menos 8 caracteres, una letra mayúscula, una letra minúscula y un número.')
                                ->password()
                                ->required(),
                        ])->columns(3),
                    Step::make('Rol de Usuario')
                        ->schema([
                            Toggle::make('is_admin')
                                ->label('Administrador'),
                            Toggle::make('is_agency')
                                ->label('Agencia'),
                            Toggle::make('is_agent')
                                ->label('Agente'),
                            Toggle::make('is_subagent')
                                ->label('Subagente'),
                            Toggle::make('is_subagent')
                                ->label('Subagente'),
                            Toggle::make('is_doctor')
                                ->label('Doctor'),
                            Select::make('departament')
                                ->label('Departamento')
                                ->options([
                                    'COMERCIAL'     => 'COMERCIAL',
                                    'COTIZACIONES'  => 'COTIZACIONES',
                                    'AFILIACIONES'  => 'AFILIACIONES',
                                    'OPERACIONES'   => 'OPERACIONES',
                                    'OPERACIONES'   => 'OPERACIONES',
                                ]),
                        ])->columns(3),
                ])->columnSpanFull()  
            ]);
    }
}
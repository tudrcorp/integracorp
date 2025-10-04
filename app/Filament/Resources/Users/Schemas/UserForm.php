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
use Filament\Schemas\Components\Fieldset;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información Principal')
                        ->schema([
                            Fieldset::make('Datos Personales del Usuario')
                                ->schema([
                                    TextInput::make('name')
                                        ->Label('Nombre y Apellido')
                                        ->required()
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('full_name', $state.toUpperCase());
                                        JS),
                                    TextInput::make('phone')
                                        ->label('Número de Teléfono')
                                        ->tel(),
                                    TextInput::make('email')
                                        ->label('Correo Electrónico')
                                        ->email()
                                        ->unique('users', 'email')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                            'unique'    => 'El correo electrónico ya existe',
                                            'email'     => 'Formato incorrecto',
                                        ]),

                                    TextInput::make('status')
                                        ->default('ACTIVO'),
                                ])->columnSpanFull()
                        ])->columns(4),
                    Step::make('Rol de Usuario')
                        ->schema([
                            Fieldset::make('Rol de Usuario')
                                ->schema([
                                    Toggle::make('is_agent')
                                        ->label('Agente'),
                                    Toggle::make('is_subagent')
                                        ->label('Subagente'),
                                    Toggle::make('is_designer')
                                        ->label('Diseñador'),
                                    Toggle::make('is_doctor')
                                        ->label('Doctor'),
                                ])->columnSpanFull(),
                            Fieldset::make('Departamento asociado al Usuario')
                                ->schema([
                                    Select::make('departament')
                                        ->label('Departamento')
                                        ->helperText('El usuario solo recibirá las notificaciones asociadas al departamento.')
                                        ->options([
                                            'COMERCIAL'     => 'COMERCIAL',
                                            'COTIZACIONES'  => 'COTIZACIONES',
                                            'AFILIACIONES'  => 'AFILIACIONES',
                                            'OPERACIONES'   => 'OPERACIONES',
                                            'MARKETING'     => 'MARKETING',
                                        ]),
                                ])->columnSpanFull(),
                        ])->columns(4),
                ])->columnSpanFull()  
            ]);
    }
}
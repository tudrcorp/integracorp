<?php

namespace App\Filament\Business\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\DateTimePicker;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion del Usuario')
                    ->description('Informacion principal del usuario INTEGRACORP.')
                    ->aside()
                    ->icon('heroicon-s-user')
                    ->schema([
                        Fieldset::make('Informacion del Usuario')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre y Apellido del usuario')
                                    ->required(),
                                TextInput::make('phone')
                                    ->label('Telefono')
                                    ->tel(),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->format('d/m/Y')
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),
                                TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email(),
                                Select::make('departament')
                                    ->label('Departamento')
                                    ->helperText('El usuario solo recibirá las notificaciones asociadas al departamento.')
                                    ->options([
                                        'COMERCIAL'     => 'COMERCIAL',
                                        'COTIZACIONES'  => 'COTIZACIONES',
                                        'AFILIACIONES'  => 'AFILIACIONES',
                                        'OPERACIONES'   => 'OPERACIONES',
                                        'ADMINISTRACION'=> 'ADMINISTRACION',
                                        'MARKETING'     => 'MARKETING',
                                        'TELEMEDICINA'  => 'TEEMEDICINA',
                                        'NEGOCIOS'      => 'NEGOCIOS',
                                    ]),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'ACTIVO'    => 'ACTIVO',
                                        'INACTIVO'  => 'INACTIVO',
                                    ]),  
                                
                            ])->columnSpanFull()->columns(3),
                    ])->columnSpanFull()->columns(3),

                Section::make('Roles del Usuario')
                    ->description('Roles asociados al usuario.')
                    ->aside()
                    ->icon('heroicon-s-user')
                    ->schema([
                        Fieldset::make('Roles')
                            ->schema([
                                Toggle::make('is_admin')
                                    ->label('Administrador'),
                                Toggle::make('is_agent')
                                    ->label('Agente'),
                                Toggle::make('is_subagent')
                                    ->label('Subagente'),
                                Toggle::make('is_agency')
                                    ->label('Agencia'),
                                Toggle::make('is_doctor')
                                    ->label('Doctor'),
                                Toggle::make('is_designer')
                                    ->label('Diseñador y Marketing'),
                                Toggle::make('is_accountManagers')
                                    ->label('Administrador de Cuentas'),
                                Toggle::make('is_superAdmin')
                                    ->label('Super Administrador'),
                                Toggle::make('is_business_admin')
                                    ->label('Administrador de Negocios'),
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }
}
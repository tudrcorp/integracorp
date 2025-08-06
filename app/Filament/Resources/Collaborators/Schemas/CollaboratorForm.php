<?php

namespace App\Filament\Resources\Collaborators\Schemas;

use App\Models\Collaborator;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Set;

class CollaboratorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('COLABORADOR')
                    ->description('Fomulario para el registro del colaborador. Campo Requerido(*)')
                    ->icon('heroicon-s-user-plus')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code')
                                ->label('Código')
                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                ->default(function () {
                                    if (Collaborator::max('id') == null) {
                                        $parte_entera = 0;
                                    } else {
                                        $parte_entera = Collaborator::max('id');
                                    }
                                    return 'TDEC-COL-000' . $parte_entera + 1;
                                })
                                ->required()
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                        ])->columnSpanFull()->columns(3),
                        TextInput::make('full_name')
                            ->label('Nombre y Apellido')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('full_name', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('dni')
                            ->label('Nro. de Identidad')
                            ->prefixIcon('heroicon-s-identification')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('dni', str_replace(['.', ',', '-'], '', $state));
                            })
                            ->live(onBlur: true)
                            ->required()
                            ->unique('collaborators', 'dni')
                            ->validationMessages([
                                'unique' => 'El Nro. de Identidad ya se encuentra registrado.',
                            ])
                            ->maxLength(255),
                        DatePicker::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('company_init_date')
                            ->label('Fecha de Ingreso')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->displayFormat('d/m/Y')
                            ->required(),
                        TextInput::make('departament')
                            ->label('Departamento')
                            ->prefixIcon('heroicon-s-building-office')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('departament', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->label('Cargo')
                            ->prefixIcon('heroicon-c-tag')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('position', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->required()
                            ->maxLength(255),
                        Select::make('sex')
                            ->label('Sexo')
                            ->prefixIcon('heroicon-o-numbered-list')
                            ->required()
                            ->live()
                            ->options([
                                'MASCULINO' => 'MASCULINO',
                                'FEMENINO'  => 'FEMENINO',
                            ]),
                        TextInput::make('phone')
                            ->label('Número de teléfono')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('coorporate_email')
                            ->label('Correo electrónico')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->unique('collaborators', 'coorporate_email')
                            ->validationMessages([
                                'unique' => 'El Correo electrónico ya se encuentra registrado.',
                            ])
                            ->maxLength(255),
                        TextInput::make('alternative_email')
                            ->label('Email Alternativo')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->unique('collaborators', 'alternative_email')
                            ->validationMessages([
                                'unique' => 'El Email Alternativo ya se encuentra registrado.',
                            ])
                            ->maxLength(255),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->prefixIcon('heroicon-m-shield-check')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255)
                            ->default('ACTIVO'),
                        TextInput::make('created_by')
                            ->label('Creado Por:')
                            ->prefixIcon('heroicon-s-user-circle')
                            ->disabled()
                            ->dehydrated()
                            ->default(Auth::user()->name)
                            ->maxLength(255),

                    ])->columnSpanFull()->columns(4),
            ]);
    }
}

<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RrhhColaboradorForm
{
    private const IOS_SECTION_CLASS = 'rounded-3xl border border-slate-200/70 bg-white/90 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/70';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Perfil')
                    ->description('Imagen de avatar utilizada en listados, asignaciones y firmas internas.')
                    ->icon('heroicon-m-user-circle')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        FileUpload::make('avatar')
                            ->label('Foto de perfil')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->circleCropper()
                            ->directory('avatars-colaboradores')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->imagePreviewHeight('240')
                            ->helperText('Formatos: JPG o PNG. Máx. 2 MB. Recomendación: imagen cuadrada con fondo claro.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos personales')
                    ->description('Identidad, talla y composición familiar del colaborador.')
                    ->icon('heroicon-m-identification')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                TextInput::make('fullName')
                                    ->label('Nombre completo')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Ej: María Fernanda Pérez')
                                    ->prefixIcon('heroicon-m-user')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                                TextInput::make('cedula')
                                    ->label('Cédula')
                                    ->maxLength(255)
                                    ->placeholder('Ej: V-12345678')
                                    ->prefixIcon('heroicon-m-identification'),
                                Select::make('sexo')
                                    ->label('Sexo')
                                    ->options([
                                        'Masculino' => 'Masculino',
                                        'Femenino' => 'Femenino',
                                        'Otro' => 'Otro',
                                    ])
                                    ->native(false)
                                    ->placeholder('Seleccione una opción'),
                                DatePicker::make('fechaNacimiento')
                                    ->label('Fecha de nacimiento')
                                    ->format('d/m/Y')
                                    ->native(false)
                                    ->placeholder('Seleccione fecha'),
                                TextInput::make('direccion')
                                    ->label('Dirección')
                                    ->maxLength(255)
                                    ->placeholder('Ej: Urb. Los Naranjos, Caracas')
                                    ->prefixIcon('heroicon-m-map-pin')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),
                                TextInput::make('nroHijos')
                                    ->label('Nº de hijos')
                                    ->numeric()
                                    ->maxLength(255)
                                    ->default(0),
                                TextInput::make('nroHijoDependiente')
                                    ->label('Nº de hijos dependientes')
                                    ->numeric()
                                    ->maxLength(255)
                                    ->default(0),
                                Select::make('tallaCamisa')
                                    ->label('Talla de camisa')
                                    ->options([
                                        'XS' => 'XS',
                                        'S' => 'S',
                                        'M' => 'M',
                                        'L' => 'L',
                                        'XL' => 'XL',
                                        'XXL' => 'XXL',
                                    ])
                                    ->native(false)
                                    ->placeholder('Seleccione talla'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos laborales')
                    ->description('Área, cargo y estado activo del perfil dentro de la empresa.')
                    ->icon('heroicon-m-briefcase')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                Select::make('departmento_id')
                                    ->label('Departamento')
                                    ->relationship('departamento', 'description', fn ($query) => $query->orderBy('description'))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Seleccione departamento'),
                                Select::make('cargo_id')
                                    ->label('Cargo')
                                    ->relationship('cargo', 'description', fn ($query) => $query->orderBy('description'))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('Seleccione cargo'),
                                DatePicker::make('fechaIngreso')
                                    ->label('Fecha de ingreso')
                                    ->format('d/m/Y')
                                    ->native(false)
                                    ->placeholder('Seleccione fecha'),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'activo' => 'Activo',
                                        'inactivo' => 'Inactivo',
                                    ])
                                    ->default('activo')
                                    ->required()
                                    ->native(false)
                                    ->placeholder('Seleccione estado'),
                                TextInput::make('sueldo')
                                    ->label('Sueldo')
                                    ->numeric()
                                    ->prefix('US$')
                                    ->placeholder('0.00'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Canales de contacto personal y corporativo actualizados.')
                    ->icon('heroicon-m-phone')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                TextInput::make('telefono')
                                    ->label('Teléfono personal')
                                    ->tel()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-phone'),
                                TextInput::make('telefonoCorporativo')
                                    ->label('Teléfono corporativo')
                                    ->tel()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-phone'),
                                TextInput::make('emailPersonal')
                                    ->label('Email personal')
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-envelope'),
                                TextInput::make('emailCorporativo')
                                    ->email()
                                    ->label('Email corporativo')
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-envelope'),
                                TextInput::make('emailAlternativo')
                                    ->label('Email alternativo')
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-envelope'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos bancarios')
                    ->description('Cuenta bancaria para nómina')
                    ->icon('heroicon-m-banknotes')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                TextInput::make('banck_id')
                                    ->label('Banco (ID o nombre)')
                                    ->maxLength(255)
                                    ->placeholder('Ej: Banco Mercantil')
                                    ->prefixIcon('heroicon-m-building-library'),
                                TextInput::make('nroCta')
                                    ->label('Nº de cuenta')
                                    ->maxLength(255)
                                    ->placeholder('Ej: 01050000000000000000')
                                    ->prefixIcon('heroicon-m-credit-card'),
                                TextInput::make('codigoCta')
                                    ->label('Código de cuenta')
                                    ->maxLength(255)
                                    ->placeholder('Ej: 0105'),
                                Select::make('tipoCta')
                                    ->label('Tipo de cuenta')
                                    ->options([
                                        'AHORRO' => 'Ahorro',
                                        'CORRIENTE' => 'Corriente',
                                    ])
                                    ->native(false)
                                    ->placeholder('Seleccione tipo'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Hidden::make('created_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated(),

                Hidden::make('updated_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated(),
            ]);
    }
}

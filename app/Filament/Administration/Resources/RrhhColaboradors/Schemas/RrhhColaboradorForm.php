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
    // protected static string $sectionClass = 'rounded-2xl shadow-sm bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm border border-gray-200/80 dark:border-gray-700/80 overflow-hidden';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar')
                    ->label('Foto de perfil')
                    ->avatar()
                    ->image()
                    ->imageEditor()
                    ->circleCropper()
                    ->directory('avatars-colaboradores')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->imagePreviewHeight('320')
                    ->helperText('Formatos: JPG, PNG. Máx. 2 MB. Se recomienda imagen cuadrada. Haga clic para editar.')
                    ->columnSpanFull(),

                Section::make('Datos personales')
                    ->description('Información de identidad y datos personales')
                    ->icon('heroicon-m-identification')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('fullName')
                                    ->label('Nombre completo')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('cedula')
                                    ->label('Cédula')
                                    ->maxLength(255),
                                Select::make('sexo')
                                    ->label('Sexo')
                                    ->options([
                                        'Masculino' => 'Masculino',
                                        'Femenino' => 'Femenino',
                                        'Otro' => 'Otro',
                                    ])
                                    ->native(false),
                                DatePicker::make('fechaNacimiento')
                                    ->label('Fecha de nacimiento')
                                    ->format('d/m/Y')
                                    ->native(false),
                                TextInput::make('direccion')
                                    ->label('Dirección')
                                    ->maxLength(255),
                                TextInput::make('nroHijos')
                                    ->label('Nº de hijos')
                                    ->numeric()
                                    ->maxLength(255),
                                TextInput::make('nroHijoDependiente')
                                    ->label('Nº de hijos dependientes')
                                    ->numeric()
                                    ->maxLength(255),
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
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos laborales')
                    ->description('Departamento, cargo y fechas de ingreso')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Select::make('departmento_id')
                                    ->label('Departamento')
                                    ->relationship('departamento', 'description', fn ($query) => $query->orderBy('description'))
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                                Select::make('cargo_id')
                                    ->label('Cargo')
                                    ->relationship('cargo', 'description', fn ($query) => $query->orderBy('description'))
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                                DatePicker::make('fechaIngreso')
                                    ->label('Fecha de ingreso')
                                    ->format('d/m/Y')
                                    ->native(false),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'activo' => 'Activo',
                                        'inactivo' => 'Inactivo',
                                    ])
                                    ->default('activo')
                                    ->required()
                                    ->native(false),
                                TextInput::make('sueldo')
                                    ->label('Sueldo')
                                    ->numeric()
                                    ->prefix('US$'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Teléfonos y correos electrónicos')
                    ->icon('heroicon-m-phone')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('telefono')
                                    ->label('Teléfono personal')
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('telefonoCorporativo')
                                    ->label('Teléfono corporativo')
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('emailPersonal')
                                    ->label('Email personal')
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('emailCorporativo')
                                    ->label('Email corporativo')
                                    ->maxLength(255),
                                TextInput::make('emailAlternativo')
                                    ->label('Email alternativo')
                                    ->email()
                                    ->maxLength(255),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos bancarios')
                    ->description('Cuenta bancaria para nómina')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('banck_id')
                                    ->label('Banco (ID o nombre)')
                                    ->maxLength(255),
                                TextInput::make('nroCta')
                                    ->label('Nº de cuenta')
                                    ->maxLength(255),
                                TextInput::make('codigoCta')
                                    ->label('Código de cuenta')
                                    ->maxLength(255),
                                Select::make('tipoCta')
                                    ->label('Tipo de cuenta')
                                    ->options([
                                        'AHORRO' => 'Ahorro',
                                        'CORRIENTE' => 'Corriente',
                                    ])
                                    ->native(false),
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

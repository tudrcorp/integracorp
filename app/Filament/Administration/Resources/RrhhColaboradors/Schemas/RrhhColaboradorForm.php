<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Schemas;

use App\Models\RrhhColaborador;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RrhhColaboradorForm
{
    private const IOS_SECTION_CLASS = 'rounded-3xl border border-slate-200/70 bg-white/90 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/70';

    private const IOS_TEXT_INPUT_CLASS = 'min-h-11 rounded-none border-slate-300/90 bg-white/95 text-sm shadow-sm transition duration-200 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/15 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-400 dark:focus:ring-sky-400/20';

    /**
     * Con native(false), Filament no aplica extraInputAttributes al botón del select (usa min-h-9 por defecto).
     * Forzamos la misma altura mínima que los TextInput (min-h-11) vía el wrapper .fi-fo-select.
     */
    private const IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS = '[&_.fi-select-input]:!min-h-11 [&_.fi-select-input-btn]:!min-h-11';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Tabs::make('rrhhColaboradorFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->tabs([
                        Tab::make('Perfil')
                            ->icon('heroicon-m-user-circle')
                            ->schema([
                                Section::make('Perfil')
                                    ->description('Avatar, funciones del puesto y contexto visible en listados, asignaciones y firmas internas.')
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
                                            ->extraAttributes([
                                                'class' => 'rounded-2xl border border-dashed border-slate-300/80 bg-slate-50/70 p-2 dark:border-slate-700 dark:bg-slate-800/50',
                                            ])
                                            ->columnSpanFull(),
                                        Textarea::make('funciones')
                                            ->label('Funciones del colaborador')
                                            ->required()
                                            ->rows(8)
                                            ->placeholder('Describe las funciones, responsabilidades y alcance del puesto.')
                                            ->helperText('Campo obligatorio. Usa este espacio para documentar el rol operativo del colaborador.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Datos personales')
                            ->icon('heroicon-m-identification')
                            ->schema([
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
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ])
                                                    ->columnSpan([
                                                        'default' => 1,
                                                        'md' => 2,
                                                    ]),
                                                TextInput::make('cedula')
                                                    ->label('Cédula')
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: V-12345678')
                                                    ->prefixIcon('heroicon-m-identification')
                                                    ->helperText('Formato sugerido: V-12345678 o E-12345678')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                Select::make('sexo')
                                                    ->label('Sexo')
                                                    ->options([
                                                        'Masculino' => 'Masculino',
                                                        'Femenino' => 'Femenino',
                                                        'Otro' => 'Otro',
                                                    ])
                                                    ->native(false)
                                                    ->placeholder('Seleccione una opción')
                                                    ->extraAttributes([
                                                        'class' => self::IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS,
                                                    ]),
                                                DatePicker::make('birth_date')
                                                    ->label('Fecha de nacimiento')
                                                    ->format('d/m/Y')
                                                    ->native(false)
                                                    ->placeholder('Seleccione fecha')
                                                    ->displayFormat('d/m/Y')
                                                    ->maxDate(now())
                                                    ->rules(['nullable', 'before_or_equal:today'])
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                        $set('age', RrhhColaborador::completedYearsFromBirthDate($state));
                                                    })
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('age')
                                                    ->label('Edad')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(150)
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->helperText('Calculada automáticamente según la fecha de nacimiento.')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('direccion')
                                                    ->label('Dirección')
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: Urb. Los Naranjos, Caracas')
                                                    ->prefixIcon('heroicon-m-map-pin')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ])
                                                    ->columnSpan([
                                                        'default' => 1,
                                                        'md' => 2,
                                                    ]),
                                                TextInput::make('nroHijos')
                                                    ->label('Nº de hijos')
                                                    ->numeric()
                                                    ->maxLength(255)
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->step(1)
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('nroHijoDependiente')
                                                    ->label('Nº de hijos dependientes')
                                                    ->numeric()
                                                    ->maxLength(255)
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->step(1)
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
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
                                                    ->placeholder('Seleccione talla')
                                                    ->extraAttributes([
                                                        'class' => self::IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS,
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Datos laborales')
                            ->icon('heroicon-m-briefcase')
                            ->schema([
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
                                                    ->placeholder('Seleccione departamento')
                                                    ->helperText('Área organizacional del colaborador')
                                                    ->extraAttributes([
                                                        'class' => self::IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS,
                                                    ]),
                                                Select::make('cargo_id')
                                                    ->label('Cargo')
                                                    ->relationship('cargo', 'description', fn ($query) => $query->orderBy('description'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->placeholder('Seleccione cargo')
                                                    ->helperText('Rol principal asignado')
                                                    ->extraAttributes([
                                                        'class' => self::IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS,
                                                    ]),
                                                DatePicker::make('fechaIngreso')
                                                    ->label('Fecha de ingreso')
                                                    ->format('d/m/Y')
                                                    ->native(false)
                                                    ->placeholder('Seleccione fecha')
                                                    ->displayFormat('d/m/Y')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                Select::make('status')
                                                    ->label('Estado')
                                                    ->options([
                                                        'activo' => 'Activo',
                                                        'inactivo' => 'Inactivo',
                                                    ])
                                                    ->default('activo')
                                                    ->required()
                                                    ->native(false)
                                                    ->placeholder('Seleccione estado')
                                                    ->extraAttributes([
                                                        'class' => self::IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS,
                                                    ]),
                                                TextInput::make('sueldo')
                                                    ->label('Sueldo')
                                                    ->numeric()
                                                    ->prefix('US$')
                                                    ->placeholder('0.00')
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Contacto')
                            ->icon('heroicon-m-phone')
                            ->schema([
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
                                                    ->prefixIcon('heroicon-m-phone')
                                                    ->placeholder('Ej: +58 412-0000000')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('telefonoCorporativo')
                                                    ->label('Teléfono corporativo')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-phone')
                                                    ->placeholder('Extensión o móvil corporativo')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('emailPersonal')
                                                    ->label('Email personal')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-envelope')
                                                    ->placeholder('nombre@correo.com')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('emailCorporativo')
                                                    ->email()
                                                    ->label('Email corporativo')
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-envelope')
                                                    ->placeholder('nombre@empresa.com')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('emailAlternativo')
                                                    ->label('Email alternativo')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-envelope')
                                                    ->placeholder('correo alterno de respaldo')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Datos bancarios')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
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
                                                    ->prefixIcon('heroicon-m-building-library')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('nroCta')
                                                    ->label('Nº de cuenta')
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: 01050000000000000000')
                                                    ->prefixIcon('heroicon-m-credit-card')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                TextInput::make('codigoCta')
                                                    ->label('Código de cuenta')
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: 0105')
                                                    ->extraInputAttributes([
                                                        'class' => self::IOS_TEXT_INPUT_CLASS,
                                                    ]),
                                                Select::make('tipoCta')
                                                    ->label('Tipo de cuenta')
                                                    ->options([
                                                        'AHORRO' => 'Ahorro',
                                                        'CORRIENTE' => 'Corriente',
                                                    ])
                                                    ->native(false)
                                                    ->placeholder('Seleccione tipo')
                                                    ->extraAttributes([
                                                        'class' => self::IOS_SELECT_MATCH_INPUT_HEIGHT_CLASS,
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Documentos')
                            ->icon('heroicon-m-document-text')
                            ->schema([
                                Section::make('Documentos')
                                    ->description('Constancias, contratos u otros archivos del expediente. Puede adjuntar varios archivos.')
                                    ->icon('heroicon-m-document-text')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        FileUpload::make('documents')
                                            ->label('Archivos adjuntos')
                                            ->multiple()
                                            ->reorderable()
                                            ->directory('rrhh-colaboradores/documentos')
                                            ->visibility('public')
                                            ->maxFiles(30)
                                            ->maxSize(5120)
                                            ->acceptedFileTypes([
                                                'application/pdf',
                                                'image/jpeg',
                                                'image/png',
                                                'image/webp',
                                                'application/msword',
                                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                            ])
                                            ->downloadable()
                                            ->openable()
                                            ->panelLayout('grid')
                                            ->imagePreviewHeight('160')
                                            ->helperText('PDF, imágenes o Word. Hasta 5 MB por archivo. Máximo 30 archivos.')
                                            ->extraAttributes([
                                                'class' => 'rounded-2xl border border-dashed border-slate-300/80 bg-slate-50/70 p-2 dark:border-slate-700 dark:bg-slate-800/50',
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Hidden::make('created_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated(),

                Hidden::make('updated_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated(),
            ]);
    }
}

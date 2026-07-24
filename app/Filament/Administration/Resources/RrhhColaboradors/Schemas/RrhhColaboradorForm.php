<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Schemas;

use App\Models\RrhhColaborador;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RrhhColaboradorForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function avatarUploadField(): FileUpload
    {
        return FileUpload::make('avatar')
            ->label('Foto de perfil')
            ->avatar()
            ->image()
            ->imageEditor()
            ->circleCropper()
            ->automaticallyOpenImageEditorForAspectRatio()
            ->directory('avatars-colaboradores')
            ->disk('public')
            ->visibility('public')
            ->maxSize(2048)
            ->imagePreviewHeight('200')
            ->helperText('Formatos: JPG o PNG. Máx. 2 MB. Recomendación: imagen cuadrada con fondo claro.')
            ->extraAttributes([
                'class' => 'rrhh-colaborador-avatar-upload',
            ])
            ->columnSpanFull();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('rrhhColaboradorFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Perfil')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Fieldset::make('Perfil del colaborador')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Funciones del puesto')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Textarea::make('funciones')
                                                    ->label('Funciones del colaborador')
                                                    ->required()
                                                    ->rows(8)
                                                    ->placeholder('Describe las funciones, responsabilidades y alcance del puesto.')
                                                    ->helperText('Campo obligatorio. Usa este espacio para documentar el rol operativo del colaborador. La foto de perfil se gestiona con el botón «Foto de perfil» en la parte superior.')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Datos personales')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Fieldset::make('Identidad personal')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos de identificación')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('fullName')
                                                    ->label('Nombre completo')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: María Fernanda Pérez')
                                                    ->prefixIcon('heroicon-m-user')
                                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                                TextInput::make('cedula')
                                                    ->label('Cédula')
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: V-12345678')
                                                    ->prefixIcon('heroicon-m-identification')
                                                    ->helperText('Formato sugerido: V-12345678 o E-12345678'),
                                                Select::make('sexo')
                                                    ->label('Sexo')
                                                    ->options([
                                                        'Masculino' => 'Masculino',
                                                        'Femenino' => 'Femenino',
                                                        'Otro' => 'Otro',
                                                    ])
                                                    ->native(false)
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione una opción')
                                                    ->prefixIcon('heroicon-m-user'),
                                                DatePicker::make('birth_date')
                                                    ->label('Fecha de nacimiento')
                                                    ->format('Y-m-d')
                                                    ->native(false)
                                                    ->placeholder('Seleccione fecha')
                                                    ->displayFormat('d/m/Y')
                                                    ->maxDate(now())
                                                    ->rules(['nullable', 'before_or_equal:today'])
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                        $set('age', RrhhColaborador::completedYearsFromBirthDate($state));
                                                    }),
                                                TextInput::make('age')
                                                    ->label('Edad')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(150)
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->helperText('Calculada automáticamente según la fecha de nacimiento.'),
                                                TextInput::make('direccion')
                                                    ->label('Dirección')
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: Urb. Los Naranjos, Caracas')
                                                    ->prefixIcon('heroicon-m-map-pin')
                                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Composición familiar y vestuario')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Familia y talla')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('nroHijos')
                                                    ->label('Nº de hijos')
                                                    ->numeric()
                                                    ->maxLength(255)
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->step(1)
                                                    ->prefixIcon('heroicon-m-user-group'),
                                                TextInput::make('nroHijoDependiente')
                                                    ->label('Nº de hijos dependientes')
                                                    ->numeric()
                                                    ->maxLength(255)
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->step(1)
                                                    ->prefixIcon('heroicon-m-heart'),
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
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione talla')
                                                    ->prefixIcon('heroicon-m-shopping-bag'),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Datos laborales')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Fieldset::make('Información laboral')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Área, cargo y estado')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Select::make('departmento_id')
                                                    ->label('Departamento')
                                                    ->relationship('departamento', 'description', fn ($query) => $query->orderBy('description'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->placeholder('Seleccione departamento')
                                                    ->helperText('Área organizacional del colaborador')
                                                    ->prefixIcon('heroicon-m-building-office-2'),
                                                Select::make('cargo_id')
                                                    ->label('Cargo')
                                                    ->relationship('cargo', 'description', fn ($query) => $query->orderBy('description'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->placeholder('Seleccione cargo')
                                                    ->helperText('Rol principal asignado')
                                                    ->prefixIcon('heroicon-m-briefcase'),
                                                DatePicker::make('fechaIngreso')
                                                    ->label('Fecha de ingreso')
                                                    ->format('d/m/Y')
                                                    ->native(false)
                                                    ->placeholder('Seleccione fecha')
                                                    ->displayFormat('d/m/Y'),
                                                Select::make('status')
                                                    ->label('Estado')
                                                    ->options([
                                                        'activo' => 'Activo',
                                                        'inactivo' => 'Inactivo',
                                                    ])
                                                    ->default('activo')
                                                    ->required()
                                                    ->native(false)
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione estado')
                                                    ->prefixIcon('heroicon-m-signal'),
                                                TextInput::make('sueldo')
                                                    ->label('Sueldo')
                                                    ->numeric()
                                                    ->prefix('US$')
                                                    ->placeholder('0.00')
                                                    ->minValue(0)
                                                    ->step(0.01)
                                                    ->helperText('Punto(.) para separar decimales.'),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Contacto')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Fieldset::make('Canales de contacto')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Teléfonos y correos')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('telefono')
                                                    ->label('Teléfono personal')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-phone')
                                                    ->placeholder('Ej: +58 412-0000000'),
                                                TextInput::make('telefonoCorporativo')
                                                    ->label('Teléfono corporativo')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-phone')
                                                    ->placeholder('Extensión o móvil corporativo'),
                                                TextInput::make('emailPersonal')
                                                    ->label('Email personal')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-envelope')
                                                    ->placeholder('nombre@correo.com'),
                                                TextInput::make('emailCorporativo')
                                                    ->email()
                                                    ->label('Email corporativo')
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-envelope')
                                                    ->placeholder('nombre@empresa.com'),
                                                TextInput::make('emailAlternativo')
                                                    ->label('Email alternativo')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-envelope')
                                                    ->placeholder('correo alterno de respaldo')
                                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Datos bancarios')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Fieldset::make('Cuenta bancaria')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos para nómina')
                                            ->extraAttributes(['class' => self::INNER_CARD])
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
                                                    ->placeholder('Ej: 0105')
                                                    ->prefixIcon('heroicon-m-hashtag'),
                                                Select::make('tipoCta')
                                                    ->label('Tipo de cuenta')
                                                    ->options([
                                                        'AHORRO' => 'Ahorro',
                                                        'CORRIENTE' => 'Corriente',
                                                    ])
                                                    ->native(false)
                                                    ->searchable()
                                                    ->preload()
                                                    ->placeholder('Seleccione tipo')
                                                    ->prefixIcon('heroicon-m-banknotes'),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Documentos')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Fieldset::make('Expediente documental')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Archivos adjuntos')
                                            ->extraAttributes(['class' => self::INNER_CARD])
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
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
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

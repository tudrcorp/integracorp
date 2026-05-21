<?php

namespace App\Filament\Operations\Resources\TelemedicineDoctors\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\TelemedicineDoctor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TelemedicineDoctorForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('telemedicineDoctorFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Perfil del médico')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Perfil del médico')
                                    ->description('Complete la ficha profesional y de contacto. Los campos con * son obligatorios.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                TextInput::make('full_name')
                                                    ->label('Nombre y apellido')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: ANA MARIA PEREZ GARCIA')
                                                    ->prefixIcon('heroicon-s-user')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('full_name', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('nro_identificacion')
                                                    ->label('Número de identificación')
                                                    ->required()
                                                    ->prefixIcon('heroicon-s-identification')
                                                    ->placeholder('Ej: 12345678')
                                                    ->maxLength(20)
                                                    ->regex('/^[0-9]+$/')
                                                    ->unique(table: TelemedicineDoctor::class, column: 'nro_identificacion', ignoreRecord: true)
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                        'unique' => 'El número de identificación ya existe.',
                                                        'regex' => 'Solo se permiten números (0-9).',
                                                    ]),
                                                Select::make('country_code')
                                                    ->label('Código país')
                                                    ->options(fn (): array => UtilsController::getCountries())
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->default('+58')
                                                    ->native(false)
                                                    ->hiddenOn('edit')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                                TextInput::make('phone')
                                                    ->label('Número de teléfono')
                                                    ->required()
                                                    ->prefixIcon('heroicon-s-phone')
                                                    ->tel()
                                                    ->placeholder('Ej: +584121234567')
                                                    ->maxLength(30)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                                                        $cleanNumber = preg_replace('/[^0-9]/', '', (string) $state);
                                                        $cleanNumber = ltrim((string) $cleanNumber, '0');

                                                        if ($cleanNumber === '') {
                                                            return;
                                                        }

                                                        $countryCode = (string) ($get('country_code') ?? '');
                                                        if ($countryCode !== '' && ! Str::startsWith((string) $state, '+')) {
                                                            $set('phone', $countryCode.$cleanNumber);

                                                            return;
                                                        }

                                                        $set('phone', '+'.$cleanNumber);
                                                    })
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->required()
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-s-envelope')
                                                    ->placeholder('doctor@correo.com')
                                                    ->unique(table: TelemedicineDoctor::class, column: 'email', ignoreRecord: true)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (?string $state, callable $set): mixed => $set('email', Str::lower(trim((string) $state))))
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                        'unique' => 'El correo electrónico ya existe.',
                                                        'email' => 'Debe ingresar un correo válido.',
                                                    ]),
                                                TextInput::make('specialty')
                                                    ->label('Especialidad')
                                                    ->required()
                                                    ->default('MÉDICO GENERAL')
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-s-academic-cap')
                                                    ->placeholder('Ej: MEDICINA INTERNA')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn (?string $state, callable $set): mixed => $set('specialty', Str::upper(trim((string) $state)))),
                                                Textarea::make('address')
                                                    ->label('Dirección')
                                                    ->rows(3)
                                                    ->columnSpanFull()
                                                    ->placeholder('Dirección completa del médico (consultorio o domicilio).')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('address', $state.toUpperCase());
                                                    JS),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Credenciales profesionales')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Credenciales profesionales')
                                    ->description('Registros profesionales obligatorios para habilitar atención médica.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                TextInput::make('code_cm')
                                                    ->label('Código CM')
                                                    ->required()
                                                    ->prefixIcon('heroicon-s-hashtag')
                                                    ->placeholder('Ej: 12345')
                                                    ->maxLength(30)
                                                    ->regex('/^[0-9]+$/')
                                                    ->unique(table: TelemedicineDoctor::class, column: 'code_cm', ignoreRecord: true)
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                        'unique' => 'El código CM ya existe.',
                                                        'regex' => 'Solo se permiten números (0-9).',
                                                    ]),
                                                TextInput::make('code_mpps')
                                                    ->label('Código MPPS')
                                                    ->required()
                                                    ->prefixIcon('heroicon-s-hashtag')
                                                    ->placeholder('Ej: 67890')
                                                    ->maxLength(30)
                                                    ->regex('/^[0-9]+$/')
                                                    ->unique(table: TelemedicineDoctor::class, column: 'code_mpps', ignoreRecord: true)
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                        'unique' => 'El código MPPS ya existe.',
                                                        'regex' => 'Solo se permiten números (0-9).',
                                                    ]),
                                                Select::make('status')
                                                    ->label('Estado')
                                                    ->required()
                                                    ->options([
                                                        'ACTIVO' => 'ACTIVO',
                                                        'INACTIVO' => 'INACTIVO',
                                                    ])
                                                    ->default('ACTIVO')
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-s-check-badge'),
                                                Select::make('managed_by')
                                                    ->label('Pertenece a')
                                                    ->required()
                                                    ->options([
                                                        'ATENMEDI' => 'ATENMEDI',
                                                        'TDG' => 'TDG',
                                                    ])
                                                    ->default('TDG')
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-s-building-office-2')
                                                    ->helperText('Define a qué unidad pertenece el médico para segmentación operativa.'),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Archivos')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Archivos')
                                    ->description('Foto y sello digital para identificación en el sistema y documentos médicos.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(12)
                                            ->schema([
                                                FileUpload::make('image')
                                                    ->label('Foto de perfil')
                                                    ->directory('telemedicina/medicos/fotos')
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(2048)
                                                    ->columnSpan(1),
                                                FileUpload::make('signature')
                                                    ->label('Sello digital')
                                                    ->directory('firmas-medicos')
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(2048)
                                                    ->helperText('Formato recomendado PNG con fondo transparente.')
                                                    ->columnSpan(1),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Hidden::make('created_by')
                    ->default(fn (): ?string => Auth::user()?->name),
                Hidden::make('updated_by')
                    ->default(fn (): ?string => Auth::user()?->name),
            ]);
    }
}

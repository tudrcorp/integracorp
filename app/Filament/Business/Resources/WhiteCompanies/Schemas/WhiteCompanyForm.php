<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Schemas;

use App\Models\Agency;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use App\Models\WhiteCompany;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class WhiteCompanyForm
{
    private const SECTION_CARD = 'rounded-[1.25rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_10px_36px_-12px_rgba(15,23,42,0.1)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_10px_36px_-12px_rgba(0,0,0,0.4)]';

    /**
     * @return array<string, string>
     */
    private static function countryDialCodeOptions(): array
    {
        return [
            '+1' => '🇺🇸 +1 (Estados Unidos)',
            '+44' => '🇬🇧 +44 (Reino Unido)',
            '+49' => '🇩🇪 +49 (Alemania)',
            '+33' => '🇫🇷 +33 (Francia)',
            '+34' => '🇪🇸 +34 (España)',
            '+39' => '🇮🇹 +39 (Italia)',
            '+7' => '🇷🇺 +7 (Rusia)',
            '+55' => '🇧🇷 +55 (Brasil)',
            '+91' => '🇮🇳 +91 (India)',
            '+86' => '🇨🇳 +86 (China)',
            '+81' => '🇯🇵 +81 (Japón)',
            '+82' => '🇰🇷 +82 (Corea del Sur)',
            '+52' => '🇲🇽 +52 (México)',
            '+58' => '🇻🇪 +58 (Venezuela)',
            '+57' => '🇨🇴 +57 (Colombia)',
            '+54' => '🇦🇷 +54 (Argentina)',
            '+56' => '🇨🇱 +56 (Chile)',
            '+51' => '🇵🇪 +51 (Perú)',
            '+502' => '🇬🇹 +502 (Guatemala)',
            '+503' => '🇸🇻 +503 (El Salvador)',
            '+504' => '🇭🇳 +504 (Honduras)',
            '+505' => '🇳🇮 +505 (Nicaragua)',
            '+506' => '🇨🇷 +506 (Costa Rica)',
            '+507' => '🇵🇦 +507 (Panamá)',
            '+593' => '🇪🇨 +593 (Ecuador)',
            '+592' => '🇬🇾 +592 (Guyana)',
            '+591' => '🇧🇴 +591 (Bolivia)',
            '+598' => '🇺🇾 +598 (Uruguay)',
            '+20' => '🇪🇬 +20 (Egipto)',
            '+27' => '🇿🇦 +27 (Sudáfrica)',
            '+234' => '🇳🇬 +234 (Nigeria)',
            '+212' => '🇲🇦 +212 (Marruecos)',
            '+971' => '🇦🇪 +971 (Emiratos Árabes)',
            '+92' => '🇵🇰 +92 (Pakistán)',
            '+880' => '🇧🇩 +880 (Bangladesh)',
            '+62' => '🇮🇩 +62 (Indonesia)',
            '+63' => '🇵🇭 +63 (Filipinas)',
            '+66' => '🇹🇭 +66 (Tailandia)',
            '+60' => '🇲🇾 +60 (Malasia)',
            '+65' => '🇸🇬 +65 (Singapur)',
            '+61' => '🇦🇺 +61 (Australia)',
            '+64' => '🇳🇿 +64 (Nueva Zelanda)',
            '+90' => '🇹🇷 +90 (Turquía)',
            '+375' => '🇧🇾 +375 (Bielorrusia)',
            '+372' => '🇪🇪 +372 (Estonia)',
            '+371' => '🇱🇻 +371 (Letonia)',
            '+370' => '🇱🇹 +370 (Lituania)',
            '+48' => '🇵🇱 +48 (Polonia)',
            '+40' => '🇷🇴 +40 (Rumania)',
            '+46' => '🇸🇪 +46 (Suecia)',
            '+47' => '🇳🇴 +47 (Noruega)',
            '+45' => '🇩🇰 +45 (Dinamarca)',
            '+41' => '🇨🇭 +41 (Suiza)',
            '+43' => '🇦🇹 +43 (Austria)',
            '+31' => '🇳🇱 +31 (Países Bajos)',
            '+32' => '🇧🇪 +32 (Bélgica)',
            '+353' => '🇮🇪 +353 (Irlanda)',
            '+380' => '🇺🇦 +380 (Ucrania)',
            '+994' => '🇦🇿 +994 (Azerbaiyán)',
            '+995' => '🇬🇪 +995 (Georgia)',
            '+976' => '🇲🇳 +976 (Mongolia)',
            '+998' => '🇺🇿 +998 (Uzbekistán)',
            '+84' => '🇻🇳 +84 (Vietnam)',
            '+856' => '🇱🇦 +856 (Laos)',
            '+374' => '🇦🇲 +374 (Armenia)',
            '+965' => '🇰🇼 +965 (Kuwait)',
            '+966' => '🇸🇦 +966 (Arabia Saudita)',
            '+972' => '🇮🇱 +972 (Israel)',
            '+963' => '🇸🇾 +963 (Siria)',
            '+961' => '🇱🇧 +961 (Líbano)',
            '+960' => '🇲🇻 +960 (Maldivas)',
            '+992' => '🇹🇯 +992 (Tayikistán)',
        ];
    }

    private static function commissionField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->helperText('Porcentaje con punto decimal (ej. 12.5).')
            ->suffix('%')
            ->numeric()
            ->step(0.01)
            ->minValue(0)
            ->maxValue(100)
            ->default(null)
            ->validationMessages([
                'numeric' => 'Debe ser un valor numérico.',
            ]);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('whiteCompanyTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Empresa')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Identidad y marca')
                                    ->description('Datos visibles de la empresa aliada y logotipo.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 3])
                                            ->schema([
                                                FileUpload::make('logo')
                                                    ->label('Logotipo')
                                                    ->directory('logo-empresa')
                                                    ->visibility('public')
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(2048)
                                                    ->helperText('PNG o JPG, máximo 2 MB. Se muestra en listados y fichas.')
                                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                                Grid::make(1)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label('Razón social o nombre comercial')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->columnSpanFull(),
                                                        TextInput::make('rif')
                                                            ->label('RIF')
                                                            ->placeholder('Ej. J-12345678-9')
                                                            ->maxLength(32)
                                                            ->helperText('Identificación fiscal según el formato de su país.'),
                                                    ])
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Contacto')
                                    ->description('Correo y teléfono de la empresa (solo en alta).')
                                    ->icon('heroicon-o-phone')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->schema([
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->email()
                                                    ->live(onBlur: true)
                                                    ->required()
                                                    ->unique(
                                                        table: WhiteCompany::class,
                                                        column: 'email',
                                                    )
                                                    ->validationMessages([
                                                        'unique' => 'Este correo ya está registrado para otra empresa aliada.',
                                                    ])
                                                    ->hiddenOn('edit')
                                                    ->helperText('Será el correo principal de contacto de la empresa.'),
                                                Grid::make(['default' => 1, 'sm' => 2])
                                                    ->schema([
                                                        Select::make('country_code')
                                                            ->label('Prefijo internacional')
                                                            ->options(self::countryDialCodeOptions())
                                                            ->searchable()
                                                            ->default('+58')
                                                            ->live(onBlur: true)
                                                            ->required()
                                                            ->hiddenOn('edit')
                                                            ->helperText('Se antepone al número al guardar.'),
                                                        TextInput::make('phone')
                                                            ->label('Teléfono')
                                                            ->prefixIcon('heroicon-s-phone')
                                                            ->tel()
                                                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/')
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'El teléfono es obligatorio.',
                                                            ])
                                                            ->live(onBlur: true)
                                                            ->placeholder('4121234567')
                                                            ->helperText('Sin espacios; puede incluir el prefijo o solo el número local.')
                                                            ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                                                                $countryCode = $get('country_code');
                                                                if ($countryCode && is_string($state) && $state !== '') {
                                                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                                    $set('phone', $countryCode.$cleanNumber);
                                                                }
                                                            }),
                                                    ])
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Ubicación')
                                    ->description('País, estado, ciudad y dirección fiscal o principal.')
                                    ->icon('heroicon-o-map-pin')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 3])
                                            ->schema([
                                                Select::make('country_id')
                                                    ->label('País')
                                                    ->live()
                                                    ->options(fn (): Collection => Country::query()->orderBy('name')->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Seleccione un país.',
                                                    ])
                                                    ->preload(),
                                                Select::make('state_id')
                                                    ->label('Estado / provincia')
                                                    ->options(function (Get $get): Collection {
                                                        return State::query()
                                                            ->where('country_id', $get('country_id'))
                                                            ->orderBy('definition')
                                                            ->pluck('definition', 'id');
                                                    })
                                                    ->afterStateUpdated(function (Set $set, $state): void {
                                                        $regionId = State::query()->where('id', $state)->value('region_id');
                                                        $region = Region::query()->where('id', $regionId)->value('definition');
                                                        $set('region', $region);
                                                    })
                                                    ->live()
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-map')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Seleccione un estado.',
                                                    ])
                                                    ->preload(),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(function (Get $get): Collection {
                                                        return City::query()
                                                            ->where('country_id', $get('country_id'))
                                                            ->where('state_id', $get('state_id'))
                                                            ->orderBy('definition')
                                                            ->pluck('definition', 'id');
                                                    })
                                                    ->searchable()
                                                    ->prefixIcon('heroicon-s-building-office-2')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Seleccione una ciudad.',
                                                    ])
                                                    ->preload(),
                                            ])
                                            ->columnSpanFull(),
                                        TextInput::make('address')
                                            ->label('Dirección')
                                            ->placeholder('Calle, número, urbanización, punto de referencia…')
                                            ->maxLength(500)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Comisiones')
                            ->icon('heroicon-o-chart-pie')
                            ->hiddenOn('edit')
                            ->schema([
                                Section::make('Porcentajes de comisión')
                                    ->description('Opcional en el alta. Valores en porcentaje respecto a operaciones TDEC / TDEV.')
                                    ->icon('heroicon-o-calculator')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->schema([
                                                self::commissionField('commission_tdec', 'Comisión TDEC (US$)'),
                                                self::commissionField('commission_tdec_renewal', 'Comisión renovación TDEC (US$)'),
                                                self::commissionField('commission_tdev', 'Comisión TDEV (US$)'),
                                                self::commissionField('commission_tdev_renewal', 'Comisión renovación TDEV (US$)'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsed(false)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Configuración')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->hiddenOn('edit')
                            ->schema([
                                Section::make('Registro en configuración')
                                    ->description('Al guardar el formulario, el sistema crea automáticamente el registro asociado en configuración usando la razón social, el RIF y el correo de la pestaña «Empresa». Revise que esos datos sean correctos antes de enviar.')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Placeholder::make('configuration_sync_hint')
                                            ->label('Sin campos adicionales')
                                            ->content('No es necesario volver a escribir nombre, RIF ni correo aquí: se reutilizan los de «Empresa» para mantener un solo origen de verdad y evitar inconsistencias.'),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Administrador')
                            ->icon('heroicon-o-user-circle')
                            ->hiddenOn('edit')
                            ->schema([
                                Section::make('Usuario administrador y agencia')
                                    ->description('Credenciales del primer usuario y código de agencia generado automáticamente.')
                                    ->icon('heroicon-o-shield-check')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->schema([
                                                TextInput::make('code_agency')
                                                    ->label('Código de agencia')
                                                    ->prefixIcon('heroicon-m-clipboard-document-check')
                                                    ->default(function (): string {
                                                        $maxId = Agency::query()->max('id');

                                                        return 'TDG-'.(100 + (int) ($maxId ?? 0) + 1);
                                                    })
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->maxLength(255)
                                                    ->helperText('Generado automáticamente; no editable.'),
                                                Select::make('agency_type')
                                                    ->label('Rol en la estructura')
                                                    ->options([
                                                        'MASTER' => 'MASTER',
                                                        'GENERAL' => 'GENERAL',
                                                    ])
                                                    ->default('MASTER')
                                                    ->required()
                                                    ->native(false)
                                                    ->helperText('Nivel de permisos del usuario administrador.'),
                                            ])
                                            ->columnSpanFull(),
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->schema([
                                                TextInput::make('email_administrador')
                                                    ->label('Correo del administrador')
                                                    ->required()
                                                    ->email()
                                                    ->autocomplete('email')
                                                    ->helperText('Con el que iniciará sesión en el panel.'),
                                                TextInput::make('password')
                                                    ->label('Contraseña')
                                                    ->password()
                                                    ->revealable()
                                                    ->rules(['nullable', 'string', 'min:8'])
                                                    ->validationMessages([
                                                        'min' => 'La contraseña debe tener al menos 8 caracteres.',
                                                    ])
                                                    ->helperText('Opcional en el alta; si la completa, use al menos 8 caracteres.'),
                                            ])
                                            ->columnSpanFull(),
                                        Hidden::make('is_whiteCompanyAdmin')->default(true),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Hidden::make('created_by')->default(fn (): ?string => Auth::user()?->name),
                Hidden::make('updated_by'),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agents\Schemas;

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\Agent;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
use App\Support\CountrySelectOptions;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgentForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const REPEATER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-sm dark:border-white/10 dark:bg-slate-900/40';

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function auditHiddenFields(): array
    {
        return [
            Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? '')->hiddenOn('edit'),
            Hidden::make('updated_by')
                ->default(fn (): string => Auth::user()?->name ?? '')
                ->hiddenOn('create'),
        ];
    }

    private static function hasDuplicatedEmail(string $email, ?int $ignoreAgentId = null): bool
    {
        $normalizedEmail = mb_strtolower(trim($email));

        $agentQuery = Agent::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail]);

        if ($ignoreAgentId !== null) {
            $agentQuery->whereKeyNot($ignoreAgentId);
        }

        return $agentQuery->exists()
            || Agency::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->exists()
            || User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->exists();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('agentFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos principales')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Clasificación y estructura')
                                    ->description('Tipo de agente, jerarquía, agencia asociada y rol.')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                Select::make('agent_type_id')
                                                    ->label('Tipo de agente')
                                                    ->options(function (): array {
                                                        return DB::table('agent_types')
                                                            ->whereIn('definition', ['AGENTE', 'SUB-AGENTE'])
                                                            ->orderBy('definition')
                                                            ->pluck('definition', 'id')
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->live()
                                                    ->preload(),
                                                Select::make('owner_agent')
                                                    ->label('Agente responsable')
                                                    ->options(fn (): array => DB::table('agents')->select('name', 'id', 'status', 'agent_type_id')->where('agent_type_id', 2)->where('status', 'ACTIVO')->pluck('name', 'id')->all())
                                                    ->searchable()
                                                    ->live()
                                                    // ->required(fn (Get $get): bool => (int) $get('agent_type_id') === 3)
                                                    // ->validationMessages([
                                                    //     'required' => 'Debe seleccionar un agente responsable cuando el tipo sea sub agente.',
                                                    // ])
                                                    ->hidden(fn (Get $get): bool => (int) $get('agent_type_id') === 2)
                                                    ->preload()
                                                    ->helperText('Solo agentes activos tipo agente principal.'),
                                                Select::make('owner_code')
                                                    ->label('¿Pertenece a una agencia?')
                                                    ->helperText('Si el agente pertenece a nuestra estructura, deje el campo vacío.')
                                                    ->options(function (): array {
                                                        return Agency::query()
                                                            ->select('code', 'agency_type_id', 'name_corporative')
                                                            ->where('status', 'ACTIVO')
                                                            ->orderBy('name_corporative')
                                                            ->get()
                                                            ->mapWithKeys(function (Agency $agency): array {
                                                                $type = AgencyType::query()->find($agency->agency_type_id)?->definition ?? '';
                                                                $agencyName = trim((string) ($agency->name_corporative ?? ''));
                                                                $code = trim((string) ($agency->code ?? ''));

                                                                if ($agencyName !== '' && $code !== '') {
                                                                    $label = "{$agencyName} — {$code}";
                                                                } elseif ($agencyName !== '') {
                                                                    $label = $agencyName;
                                                                } else {
                                                                    $label = $type !== '' && $code !== ''
                                                                        ? "{$type} — {$code}"
                                                                        : $code;
                                                                }

                                                                return [$agency->code => $label];
                                                            })
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('role')
                                                    ->label('Rol del agente')
                                                    ->options([
                                                        'AGENTE-DE-CORRETAJE' => 'AGENTE-DE-CORRETAJE',
                                                        'EJECUTIVO' => 'EJECUTIVO',
                                                    ])
                                                    ->helperText('Si no selecciona un rol, el sistema asignará AGENTE-DE-CORRETAJE por defecto.')
                                                    ->searchable()
                                                    ->default('AGENTE-DE-CORRETAJE')
                                                    // ->required()
                                                    // ->validationMessages([
                                                    //     'required' => 'Campo requerido',
                                                    // ])
                                                    ->preload(),
                                                Select::make('ownerAccountManagers')
                                                    ->hidden(fn (): bool => ! in_array('SUPERADMIN', Auth::user()?->departament ?? [], true))
                                                    ->label('Account manager (administrador de cuenta)')
                                                    ->options(fn (): array => User::query()->where('is_accountManagers', true)->orderBy('name')->pluck('name', 'id')->all())
                                                    ->searchable()
                                                    // ->required()
                                                    // ->validationMessages([
                                                    //     'required' => 'Campo requerido',
                                                    // ])
                                                    ->preload(),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Identificación y datos personales')
                                    ->description('Nombre, documentos, sexo, fechas y correo.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nombre / razón social')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('name', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    // ->required()
                                                    // ->validationMessages([
                                                    //     'required' => 'Campo requerido',
                                                    // ])
                                                    ->maxLength(255),
                                                TextInput::make('rif')
                                                    ->label('RIF (si posee)')
                                                    ->prefix('J-')
                                                    ->nullable()
                                                    ->numeric(),
                                                    // ->requiredWithout('ci')
                                                    // ->rule(function (?Agent $record): Closure {
                                                    //     return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                                    //         if (! is_string($value) && ! is_int($value)) {
                                                    //             return;
                                                    //         }

                                                    //         $normalizedRif = trim((string) $value);

                                                    //         if ($normalizedRif === '') {
                                                    //             return;
                                                    //         }

                                                    //         $agentQuery = Agent::query()->where('rif', $normalizedRif);

                                                    //         if ($record !== null) {
                                                    //             $agentQuery->whereKeyNot($record->id);
                                                    //         }

                                                    //         if ($agentQuery->exists() || Agency::query()->where('rif', $normalizedRif)->exists()) {
                                                    //             $fail('El RIF ya se encuentra registrado en la tabla de agentes o agencias. Por favor intente con otro.');
                                                    //         }
                                                    //     };
                                                    // })
                                                    // ->validationMessages([
                                                    //     'numeric' => 'El campo es numérico',
                                                    //     'required_without' => 'Debe registrar RIF o cédula de identidad para continuar.',
                                                    // ]),
                                                TextInput::make('ci')
                                                    ->label('Cédula de identidad')
                                                    ->prefix('V/E/C')
                                                    ->nullable()
                                                    // ->requiredWithout('rif')
                                                    // ->unique(
                                                    //     ignoreRecord: true,
                                                    //     table: Agent::class,
                                                    //     column: 'ci',
                                                    // )
                                                    ->numeric(),
                                                    // ->validationMessages([
                                                    //     'required_without' => 'Debe registrar cédula de identidad o RIF para continuar.',
                                                    //     'numeric' => 'El campo es numérico',
                                                    //     'unique' => 'La cédula de identidad ya existe en la tabla de agentes. Por favor intente con otra',
                                                    // ]),
                                                Select::make('sex')
                                                    ->label('Sexo')
                                                    ->live()
                                                    // ->required()
                                                    // ->options([
                                                    //     'MASCULINO' => 'MASCULINO',
                                                    //     'FEMENINO' => 'FEMENINO',
                                                    // ])
                                                    ->searchable()
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                    ])
                                                    ->preload(),
                                                    Select::make('country_code')
                                                    // ->required()
                                                    ->label('Código de país')
                                                    ->options([
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
                                                    ])
                                                    ->searchable()
                                                    ->default('+58')
                                                    ->live(onBlur: true)
                                                    // ->validationMessages([
                                                    //     'required' => 'Campo requerido',
                                                    // ])
                                                    ->hiddenOn('edit'),
                                                TextInput::make('phone')
                                                    ->tel()
                                                    ->label('Número de teléfono')
                                                    // ->required()
                                                    // ->validationMessages([
                                                    //     'required' => 'Campo requerido',
                                                    // ])
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                                                        $countryCode = $get('country_code');
                                                        if ($countryCode) {
                                                            $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', (string) $state), '0');
                                                            $set('phone', $countryCode.$cleanNumber);
                                                        }
                                                    }),
                                                DatePicker::make('birth_date')
                                                    ->label('Fecha de nacimiento')
                                                    // ->required()
                                                    ->displayFormat('d/m/Y'),
                                                    // ->validationMessages([
                                                    //     'required' => 'Campo requerido',
                                                    // ]),
                                                DatePicker::make('company_init_date')
                                                    ->label('Fecha de ingreso')
                                                    ->displayFormat('d/m/Y')
                                                    ->default(now()->subYears(18)),
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->email()
                                                    ->rule(function (?Agent $record): Closure {
                                                        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                                            if (! is_string($value) || blank($value)) {
                                                                return;
                                                            }

                                                            if (self::hasDuplicatedEmail($value, $record?->id)) {
                                                                $fail('El correo electrónico ya se encuentra registrado en las tablas de agentes, agencias o usuarios. Por favor intente con otro.');
                                                            }
                                                        };
                                                    })
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                        'email' => 'El campo es un email',
                                                    ])
                                                    ->maxLength(255)
                                                    ->hiddenOn('edit'),
                                                
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Ubicación y contacto')
                                    ->description('País, estado, ciudad, teléfono y redes.')
                                    ->icon('heroicon-o-map-pin')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                            
                                                Fieldset::make('Dirección en Venezuela')
                                                    ->schema([

                                                        Select::make('country_id')
                                                            ->label('País')
                                                            ->live()
                                                            ->afterStateUpdated(function (Set $set): void {
                                                                $set('state_id', null);
                                                                $set('city_id', null);
                                                                $set('region', null);
                                                            })
                                                            ->options(Country::all()->pluck('name', 'id'))
                                                            ->searchable()
                                                            ->default(189)
                                                            // Venezuela
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                        Select::make('state_id')
                                                            ->label('Estado')
                                                            ->options(function (Get $get): array {
                                                                if (blank($get('country_id'))) {
                                                                    return [];
                                                                }

                                                                return State::query()
                                                                    ->where('country_id', $get('country_id'))
                                                                    ->orderBy('definition')
                                                                    ->pluck('definition', 'id')
                                                                    ->all();
                                                            })
                                                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                                $regionId = State::query()->whereKey($state)->value('region_id');
                                                                $region = Region::query()->whereKey($regionId)->value('definition');
                                                                $set('region', $region);
                                                                $set('city_id', null);
                                                            })
                                                            ->live()
                                                            ->searchable()
                                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                                            ->preload(),
                                                        Select::make('city_id')
                                                            ->label('Ciudad')
                                                            ->options(function (Get $get) {
                                                                return City::where('country_id', $get('country_id'))->where('state_id', $get('state_id'))->pluck('definition', 'id');
                                                            })
                                                            ->searchable()
                                                            ->prefixIcon('heroicon-s-globe-europe-africa')
                                                            ->preload(),

                                                        Textarea::make('address')
                                                            ->columnSpanFull()
                                                            ->label('Dirección')
                                                            ->afterStateUpdatedJs(<<<'JS'
                                                            $set('address', $state.toUpperCase());
                                                        JS)
                                                            ->live(onBlur: true)
                                                            ->rows(1)
                                                            ->maxLength(255),

                                                    ])->columnSpanFull()->columns(3),
                                                Fieldset::make('Dirección en Otros Paises')
                                                    ->schema([

                                                        Select::make('country_other_country')
                                                            ->label('País')
                                                            ->live()
                                                            ->default(185)
                                                            ->afterStateUpdated(function (Set $set): void {
                                                                $set('state_id', null);
                                                                $set('city_id', null);
                                                                $set('region', null);
                                                            })
                                                            ->options(fn (): array => CountrySelectOptions::exceptVenezuelaInSpanish())
                                                            ->searchable()
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                        TextInput::make('state_other_country')
                                                            ->label('Estado')
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                        TextInput::make('city_other_country')
                                                            ->label('Ciudad')
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),
                                                        TextInput::make('postal_code_other_country')
                                                            ->label('Código Postal')
                                                            ->prefixIcon('heroicon-s-identification')
                                                            ->maxLength(255),
                                                        Textarea::make('address_other_country')
                                                            ->columnSpanFull()
                                                            ->rows(1)
                                                            ->label('Dirección')
                                                            ->afterStateUpdatedJs(<<<'JS'
                                                            $set('address_other_country', $state.toUpperCase());
                                                        JS)
                                                            ->live(onBlur: true)
                                                            ->maxLength(255),

                                                    ])->columnSpanFull()->columns(4),

                                                TextInput::make('user_instagram')
                                                    ->label('Usuario de Instagram')
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Banca nacional')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Section::make('Datos bancarios en moneda nacional')
                                    ->description('Beneficiario y cuentas en bolívares y divisas locales.')
                                    ->icon('heroicon-o-banknotes')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('local_beneficiary_name')
                                                    ->label('Nombre / razón social del beneficiario')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('local_beneficiary_name', $state.toUpperCase());
                                                    JS)
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),
                                                TextInput::make('local_beneficiary_rif')
                                                    ->label('CI / RIF del beneficiario')
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numérico',
                                                    ])
                                                    ->maxLength(255),
                                                TextInput::make('local_beneficiary_phone_pm')
                                                    ->label('Teléfono pago móvil del beneficiario')
                                                    ->tel()
                                                    ->helperText('Formato: 04121234567, 04241869168')
                                                    ->mask('09999999999')
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ]),
                                        Fieldset::make('Cuenta nacional, moneda nacional (Bs.)')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('local_beneficiary_account_number')
                                                    ->label('Número de cuenta del beneficiario'),
                                                Select::make('local_beneficiary_account_bank')
                                                    ->label('Banco del beneficiario')
                                                    ->options([
                                                        'BANCO DE VENEZUELA' => 'BANCO DE VENEZUELA',
                                                        'BANCO BICENTENARIO' => 'BANCO BICENTENARIO',
                                                        'BANCO MERCANTIL' => 'BANCO MERCANTIL',
                                                        'BANCO PROVINCIAL' => 'BANCO PROVINCIAL',
                                                        'BANCO CARONI' => 'BANCO CARONI',
                                                        'BANCO DEL CARIBE' => 'BANCO DEL CARIBE',
                                                        'BANCO DEL TESORO' => 'BANCO DEL TESORO',
                                                        'BANCO NACIONAL DE CREDITO' => 'BANCO NACIONAL DE CREDITO',
                                                        'BANESCO' => 'BANESCO',
                                                        'FONDO COMUN' => 'FONDO COMUN',
                                                        'BANCO CANARIAS' => 'BANCO CANARIAS',
                                                        'BANCO DEL SUR' => 'BANCO DEL SUR',
                                                        'BANCO AGRICOLA DE VENEZUELA' => 'BANCO AGRICOLA DE VENEZUELA',
                                                        'BANPLUS' => 'BANPLUS',
                                                        'MI BANCO' => 'MI BANCO',
                                                        'BANCAMIGA' => 'BANCAMIGA',
                                                        'BANFANB' => 'BANFANB',
                                                        'BANCARIBE' => 'BANCARIBE',
                                                        'BANCO ACTIVO' => 'BANCO ACTIVO',
                                                    ]),
                                                Select::make('local_beneficiary_account_type')
                                                    ->label('Tipo de cuenta del beneficiario')
                                                    ->options([
                                                        'AHORRO' => 'AHORRO',
                                                        'CORRIENTE' => 'CORRIENTE',
                                                    ]),
                                            ])
                                            ->columns(3),
                                        Fieldset::make('Cuenta nacional, moneda internacional (US$, EUR)')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('local_beneficiary_account_number_mon_inter')
                                                    ->label('Número de cuenta del beneficiario'),
                                                Select::make('local_beneficiary_account_bank_mon_inter')
                                                    ->label('Banco del beneficiario')
                                                    ->options([
                                                        'BANCO DE VENEZUELA' => 'BANCO DE VENEZUELA',
                                                        'BANCO BICENTENARIO' => 'BANCO BICENTENARIO',
                                                        'BANCO MERCANTIL' => 'BANCO MERCANTIL',
                                                        'BANCO PROVINCIAL' => 'BANCO PROVINCIAL',
                                                        'BANCO CARONI' => 'BANCO CARONI',
                                                        'BANCO DEL CARIBE' => 'BANCO DEL CARIBE',
                                                        'BANCO DEL TESORO' => 'BANCO DEL TESORO',
                                                        'BANCO NACIONAL DE CREDITO' => 'BANCO NACIONAL DE CREDITO',
                                                        'BANESCO' => 'BANESCO',
                                                        'FONDO COMUN' => 'FONDO COMUN',
                                                        'BANCO CANARIAS' => 'BANCO CANARIAS',
                                                        'BANCO DEL SUR' => 'BANCO DEL SUR',
                                                        'BANCO AGRICOLA DE VENEZUELA' => 'BANCO AGRICOLA DE VENEZUELA',
                                                        'BANPLUS' => 'BANPLUS',
                                                        'MI BANCO' => 'MI BANCO',
                                                        'BANCAMIGA' => 'BANCAMIGA',
                                                        'BANFANB' => 'BANFANB',
                                                        'BANCARIBE' => 'BANCARIBE',
                                                        'BANCO ACTIVO' => 'BANCO ACTIVO',
                                                    ]),
                                                Select::make('local_beneficiary_account_type_mon_inter')
                                                    ->label('Tipo de cuenta del beneficiario')
                                                    ->options([
                                                        'AHORRO' => 'AHORRO',
                                                        'CORRIENTE' => 'CORRIENTE',
                                                    ]),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Banca extranjera')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make('Datos bancarios en moneda extranjera')
                                    ->description('Cuenta internacional del beneficiario.')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('extra_beneficiary_name')
                                                    ->label('Nombre / razón social')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('extra_beneficiary_name', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),
                                                TextInput::make('extra_beneficiary_ci_rif')
                                                    ->label('Nro. CI / RIF / ID / pasaporte')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numérico',
                                                    ])
                                                    ->maxLength(255),
                                                TextInput::make('extra_beneficiary_account_number')
                                                    ->label('Número de cuenta')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numérico',
                                                    ])
                                                    ->live()
                                                    ->maxLength(255),
                                                Select::make('extra_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->searchable()
                                                    ->preload()
                                                    ->options([
                                                        'FACEBANK INTERNATIONAL' => 'FACEBANK INTERNATIONAL',
                                                        'JPMORGAN CHASE & CO' => 'JPMORGAN CHASE & CO',
                                                        'BANK OF AMERICA' => 'BANK OF AMERICA',
                                                        'WELLS FARGO' => 'WELLS FARGO',
                                                        'CITIBANK (CITIGROUP)' => 'CITIBANK (CITIGROUP)',
                                                        'U.S. BANK' => 'U.S. BANK',
                                                        'PNC FINANCIAL SERVICES' => 'PNC FINANCIAL SERVICES',
                                                        'TRUIST FINANCIAL CORPORATION' => 'TRUIST FINANCIAL CORPORATION',
                                                        'CAPITAL ONE' => 'CAPITAL ONE',
                                                        'TD BANK (TORONTO-DOMINION BANK)' => 'TD BANK (TORONTO-DOMINION BANK)',
                                                        'HSBC BANK USA' => 'HSBC BANK USA',
                                                        'FIFTH THIRD BANK' => 'FIFTH THIRD BANK',
                                                        'REGIONS FINANCIAL CORPORATION' => 'REGIONS FINANCIAL CORPORATION',
                                                        'HUNTINGTON NATIONAL BANK' => 'HUNTINGTON NATIONAL BANK',
                                                        'NAVY FEDERAL CREDIT UNION' => 'NAVY FEDERAL CREDIT UNION',
                                                        'STATE EMPLOYEES CREDIT UNION (SECU)' => 'STATE EMPLOYEES CREDIT UNION (SECU)',
                                                        'BANCO NACIONAL DE PANAMÁ (BNP)' => 'BANCO NACIONAL DE PANAMÁ (BNP)',
                                                        'CAJA DE AHORROS' => 'CAJA DE AHORROS',
                                                        'BANCO GENERAL' => 'BANCO GENERAL',
                                                        'GLOBAL BANK' => 'GLOBAL BANK',
                                                        'BANESCO PANAMÁ' => 'BANESCO PANAMÁ',
                                                        'METROBANK' => 'METROBANK',
                                                        'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)' => 'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)',
                                                        'HSBC BANK PANAMÁ' => 'HSBC BANK PANAMÁ',
                                                        'SCOTIABANK PANAMÁ' => 'SCOTIABANK PANAMÁ',
                                                        'CITIBANK PANAMÁ' => 'CITIBANK PANAMÁ',
                                                        'BANCO SANTANDER PANAMÁ' => 'BANCO SANTANDER PANAMÁ',
                                                        'BANCO DAVIVIENDA PANAMÁ' => 'BANCO DAVIVIENDA PANAMÁ',
                                                        'BANCO ALIADO' => 'BANCO ALIADO',
                                                        'MULTIBANK' => 'MULTIBANK',
                                                        'BANCAMIGA' => 'BANCAMIGA',
                                                        'BANCO DEL TESORO' => 'BANCO DEL TESORO',
                                                        'PROVINCIAL' => 'PROVINCIAL',
                                                    ]),
                                                TextInput::make('extra_beneficiary_address')
                                                    ->label('Dirección')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('extra_beneficiary_address', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),
                                                Select::make('extra_beneficiary_account_type')
                                                    ->label('Tipo de cuenta')
                                                    ->searchable()
                                                    ->preload()
                                                    ->options([
                                                        'CUENTA DE CHEQUES (CHECKING ACCOUNT)' => 'CUENTA DE CHEQUES (CHECKING ACCOUNT)',
                                                        'CUENTA DE AHORROS (SAVINGS ACCOUNT)' => 'CUENTA DE AHORROS (SAVINGS ACCOUNT)',
                                                        'CUENTA CORRIENTE (CURRENT ACCOUNT)' => 'CUENTA CORRIENTE (CURRENT ACCOUNT)',
                                                        'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)' => 'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)',
                                                        'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)' => 'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)',
                                                        'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)' => 'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)',
                                                        'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)' => 'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)',
                                                        'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)' => 'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)',
                                                        'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)' => 'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)',
                                                        'CUENTA CONJUNTA (JOINT ACCOUNT)' => 'CUENTA CONJUNTA (JOINT ACCOUNT)',
                                                        'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)' => 'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)',
                                                        'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)' => 'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)',
                                                        'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)' => 'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)',
                                                    ]),
                                                TextInput::make('extra_beneficiary_route')
                                                    ->label('Ruta')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('extra_beneficiary_route', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),
                                                TextInput::make('extra_beneficiary_swift')
                                                    ->label('SWIFT')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('extra_beneficiary_swift', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),
                                                TextInput::make('extra_beneficiary_zelle')
                                                    ->label('Zelle')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('extra_beneficiary_zelle', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Comisiones')
                            ->icon('heroicon-o-chart-pie')
                            ->schema([
                                Section::make('Comisiones TDEC / TDEV')
                                    ->description('Porcentajes y activación de esquemas.')
                                    ->icon('heroicon-o-calculator')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                Toggle::make('tdec')
                                                    ->label('TDEC')
                                                    ->inline(false)
                                                    ->onIcon('heroicon-s-check')
                                                    ->onColor('success'),
                                                Toggle::make('tdev')
                                                    ->label('TDEV')
                                                    ->inline(false)
                                                    ->onIcon('heroicon-s-check')
                                                    ->onColor('success'),
                                                TextInput::make('commission_tdec')
                                                    ->label('Comisión TDEC US$')
                                                    // ->required()
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->numeric(),
                                                    // ->validationMessages([
                                                    //     'numeric' => 'Campo tipo numérico.',
                                                    // ]),
                                                TextInput::make('commission_tdec_renewal')
                                                    ->label('Comisión renovación TDEC US$')
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->required()
                                                    ->numeric(),
                                                    // ->validationMessages([
                                                    //     'numeric' => 'Campo tipo numérico.',
                                                    // ]),
                                                TextInput::make('commission_tdev')
                                                    ->label('Comisión TDEV US$')
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->numeric(),
                                                    // ->validationMessages([
                                                    //     'numeric' => 'Campo tipo numérico.',
                                                    // ]),
                                                TextInput::make('commission_tdev_renewal')
                                                    ->label('Comisión renovación TDEV US$')
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->numeric(),
                                                    // ->validationMessages([
                                                    //     'numeric' => 'Campo tipo numérico.',
                                                    // ]),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Observaciones')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Bitácora')
                                    ->description('Notas del analista sobre reuniones y contactos con el agente.')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Repeater::make('observationCommercialStructures')
                                            ->label('Observaciones')
                                            ->relationship()
                                            ->extraAttributes([
                                                'class' => self::REPEATER_CARD,
                                            ])
                                            ->table([
                                                TableColumn::make('Observación / notas')->width('80%'),
                                                TableColumn::make('Responsable')->width('10%'),
                                                TableColumn::make('Fecha')->width('10%'),
                                            ])
                                            ->schema([
                                                Textarea::make('observation')
                                                    ->label('Observación')
                                                    ->autosize(),
                                                TextInput::make('created_by')
                                                    ->label('Responsable')
                                                    ->default(fn (): string => Auth::user()?->name ?? '')
                                                    ->disabled()
                                                    ->dehydrated(),
                                                TextInput::make('date')
                                                    ->default(now()->format('d/m/Y H:i:s'))
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ])
                                            ->deletable(function (): bool {
                                                $user = Auth::user()?->departament ?? [];

                                                return in_array('SUPERADMIN', $user, true);
                                            })
                                            ->addActionLabel('Agregar observación')
                                            ->columnSpanFull()
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->reorderable(),
                                    ])
                                    ->collapsible(),
                            ]),
                    ]),

                ...self::auditHiddenFields(),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agencies\Schemas;

use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
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

class AgencyForm
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

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('agencyFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos principales')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Identificación y jerarquía')
                                    ->description('Código, tipo de agencia, agencia master y account manager.')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('code')
                                                    ->label('Código')
                                                    ->default(function (): string {
                                                        $maxId = Agency::query()->max('id');
                                                        $base = $maxId === null ? 100 : 100 + (int) $maxId;

                                                        return 'TDG-'.($base + 1);
                                                    })
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->maxLength(255),
                                                Select::make('agency_type_id')
                                                    ->label('Tipo de agencia')
                                                    ->options(fn (): array => AgencyType::query()->orderBy('definition')->pluck('definition', 'id')->all())
                                                    ->searchable()
                                                    ->live()
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                    ])
                                                    ->preload(),
                                                Select::make('select_owner_code')
                                                    ->label('Jerarquía')
                                                    ->options(function (): array {
                                                        return Agency::query()
                                                            ->select('code', 'agency_type_id')
                                                            ->where('agency_type_id', 1)
                                                            ->get()
                                                            ->mapWithKeys(function ($agency): array {
                                                                $type = AgencyType::query()->find($agency->agency_type_id)?->definition ?? '';

                                                                return [$agency->code => "{$type} - {$agency->code}"];
                                                            })
                                                            ->all();
                                                    })
                                                    ->hidden(fn (Get $get): bool => (int) $get('agency_type_id') === 1 || $get('agency_type_id') === null)
                                                    ->helperText('Solo agencias master. Si lo deja vacío, el sistema usará TDG-100 como agencia master.')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        if ($state === null) {
                                                            $set('owner_code', 'TDG-100');

                                                            return;
                                                        }

                                                        $set('owner_code', $state);
                                                    })
                                                    ->searchable()
                                                    ->live()
                                                    ->preload()
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                                Select::make('ownerAccountManagers')
                                                    ->hidden(fn (): bool => ! in_array('SUPERADMIN', Auth::user()?->departament ?? [], true))
                                                    ->label('Account manager')
                                                    ->options(fn (): array => User::query()->where('is_accountManagers', true)->orderBy('name')->pluck('name', 'id')->all())
                                                    ->searchable()
                                                    ->preload(),
                                                Hidden::make('owner_code')
                                                    ->live()
                                                    ->default('TDG-100'),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Datos corporativos y representante')
                                    ->description('Razón social, RIF, contacto y representante legal.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('name_corporative')
                                                    ->label('Razón social')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('name', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                    ])
                                                    ->maxLength(255),
                                                TextInput::make('rif')
                                                    ->label('RIF')
                                                    ->prefix('J-')
                                                    ->numeric()
                                                    ->unique(
                                                        table: Agency::class,
                                                        column: 'rif'
                                                    )
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                        'numeric' => 'El campo es numerico',
                                                        'unique' => 'El rif ya se encuentra registrado en la tabla de agencias. Por favor intente con otro',
                                                    ]),
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->email()
                                                    ->unique(
                                                        table: Agency::class,
                                                        column: 'email'
                                                    )
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido',
                                                        'email' => 'El campo es un email',
                                                        'unique' => 'El email ya se encuentra registrado en la tabla de agencias. Por favor intente con otro',
                                                    ])
                                                    ->maxLength(255),
                                                TextInput::make('name_representative')
                                                    ->label('Nombre del representante')
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->maxLength(255)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('name_representative', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('ci_responsable')
                                                    ->label('Cédula del representante')
                                                    ->prefix('V-')
                                                    ->numeric()
                                                    ->unique(
                                                        ignoreRecord: true,
                                                        table: 'agencies',
                                                        column: 'ci_responsable',
                                                    )
                                                    ->validationMessages([
                                                        'unique' => 'La cedula del responsable ya se encuentra registrado.',
                                                        'required' => 'Campo requerido',
                                                        'numeric' => 'El campo es numerico',
                                                    ]),
                                                DatePicker::make('brithday_date')
                                                    ->label('Fecha de nacimiento del representante')
                                                    ->format('d/m/Y')
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ]),
                                                DatePicker::make('anniversary_date')
                                                    ->label('Fecha de aniversario de la agencia')
                                                    ->format('d/m/Y')
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ]),
                                                TextInput::make('address')
                                                    ->label('Dirección')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('address', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Ubicación y contacto principal')
                                    ->description('Teléfono, país, estado, ciudad y redes.')
                                    ->icon('heroicon-o-map-pin')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                Select::make('country_code')
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
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->hiddenOn('edit'),
                                                TextInput::make('phone')
                                                    ->tel()
                                                    ->label('Número de teléfono')
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                                                        $countryCode = $get('country_code');
                                                        if ($countryCode) {
                                                            $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', (string) $state), '0');
                                                            $set('phone', $countryCode.$cleanNumber);
                                                        }
                                                    }),
                                                Select::make('country_id')
                                                    ->label('País')
                                                    ->live()
                                                    ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                                                    ->searchable()
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->preload(),
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(function (Get $get): array {
                                                        return State::query()->where('country_id', $get('country_id'))->orderBy('definition')->pluck('definition', 'id')->all();
                                                    })
                                                    ->afterStateUpdated(function (Set $set, $state): void {
                                                        $regionId = State::query()->where('id', $state)->value('region_id');
                                                        $region = Region::query()->where('id', $regionId)->value('definition');
                                                        $set('region', $region);
                                                    })
                                                    ->live()
                                                    ->searchable()
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->preload(),
                                                TextInput::make('region')
                                                    ->label('Región')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->maxLength(255),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(function (Get $get): array {
                                                        return City::query()
                                                            ->where('country_id', $get('country_id'))
                                                            ->where('state_id', $get('state_id'))
                                                            ->orderBy('definition')
                                                            ->pluck('definition', 'id')
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->validationMessages([
                                                        'required' => 'Campo Requerido',
                                                    ])
                                                    ->preload(),
                                                TextInput::make('user_instagram')
                                                    ->label('Usuario de Instagram')
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Contacto secundario')
                            ->icon('heroicon-o-user-plus')
                            ->schema([
                                Section::make('Contacto alternativo')
                                    ->description('Datos de una segunda persona o canal de contacto.')
                                    ->icon('heroicon-o-phone')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('name_contact_2')
                                                    ->label('Nombre / razón social')
                                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                        $set('name_contact_2', strtoupper((string) $state));
                                                    })
                                                    ->live(onBlur: true)
                                                    ->maxLength(255),
                                                TextInput::make('email_contact_2')
                                                    ->label('Correo secundario')
                                                    ->email()
                                                    ->validationMessages([
                                                        'email' => 'Campo formato email',
                                                    ])
                                                    ->maxLength(255),
                                                Select::make('country_code_2')
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
                                                    ->live(onBlur: true)
                                                    ->searchable()
                                                    ->preload()
                                                    ->default('+58'),
                                                TextInput::make('phone_contact_2')
                                                    ->tel()
                                                    ->label('Número de teléfono')
                                                    ->live(onBlur: true)
                                                    ->validationMessages([
                                                        'numeric' => 'El campo es numerico',
                                                    ])
                                                    ->afterStateUpdated(function ($state, callable $set, Get $get): void {
                                                        $countryCode = $get('country_code_2');
                                                        if ($countryCode) {
                                                            $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', (string) $state), '0');
                                                            $set('phone_contact_2', $countryCode.$cleanNumber);
                                                        }
                                                    })
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
                                                        'numeric' => 'Campo tipo numerico',
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
                                                        'numeric' => 'Campo tipo numeric',
                                                    ])
                                                    ->maxLength(255),
                                                TextInput::make('extra_beneficiary_account_number')
                                                    ->label('Número de cuenta')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numerico',
                                                    ])
                                                    ->maxLength(255),
                                                Select::make('extra_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->searchable()
                                                    ->preload()
                                                    ->options([
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
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numerico.',
                                                    ]),
                                                TextInput::make('commission_tdec_renewal')
                                                    ->label('Comisión renovación TDEC US$')
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numerico.',
                                                    ]),
                                                TextInput::make('commission_tdev')
                                                    ->label('Comisión TDEV US$')
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numerico.',
                                                    ]),
                                                TextInput::make('commission_tdev_renewal')
                                                    ->label('Comisión renovación TDEV US$')
                                                    ->helperText('Valor en porcentaje. Use punto como separador decimal.')
                                                    ->prefix('%')
                                                    ->numeric()
                                                    ->validationMessages([
                                                        'numeric' => 'Campo tipo numerico.',
                                                    ]),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Observaciones')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Bitácora')
                                    ->description('Notas del analista sobre reuniones y contactos con la agencia.')
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

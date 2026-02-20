<?php

namespace App\Filament\Business\Resources\Agencies\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\AgencyType;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class AgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('AGENCIAS')
                    ->collapsible()
                    ->description('Fomulario para el registro de agencias. Campo Requerido(*)')
                    ->icon('heroicon-s-building-library')
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (Agency::max('id') == null) {
                                    $parte_entera = 100;
                                } else {
                                    $parte_entera = 100 + Agency::max('id');
                                }
                                return 'TDG-' . $parte_entera + 1;
                            })
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        Select::make('agency_type_id')
                            ->label('Tipo de agencia')
                            ->options(AgencyType::all()->pluck('definition', 'id'))
                            ->searchable()
                            ->live()
                            ->validationMessages([
                                'required' => 'Campo requerido',
                            ])
                            ->preload(),
                        Select::make('select_owner_code')
                            ->label('Jerarquia')
                            ->options(function (Get $get) {
                                return Agency::select('code', 'agency_type_id')
                                    ->where('agency_type_id', 1)
                                    ->get()
                                    ->mapWithKeys(function ($agency) {
                                        $type = AgencyType::find($agency->agency_type_id)->definition;
                                        return [$agency->code => "{$type} - {$agency->code}"];
                                    });
                            })
                            ->hidden(fn(Get $get) => $get('agency_type_id') == 1 || $get('agency_type_id') == null)
                            ->helperText('Esta lista despliega solo las agencias master. Si Usted decide dejar este campo vacio, el sistema tomara TDG-100 como agencia master.')
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state == null) {
                                    return $set('owner_code', 'TDG-100');
                                }
                                return $set('owner_code', $state);
                            })
                            ->searchable()
                            ->live()
                            ->preload(),
                        //La seleccion el account manager solo la puede ver el administrador del modulo de negocios
                        Select::make('ownerAccountManagers')

                            //condicion para ocultar el campo   
                            ->hidden(fn() => !in_array('SUPERADMIN', auth()->user()->departament))

                            ->label('Acount Manager')
                            ->options(function (Get $get) {
                                return User::where('is_accountManagers', true)->get()->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload(),
                        Hidden::make('owner_code')
                            ->live()
                            ->default('TDG-100'),
                        Hidden::make('created_by')->default(Auth::user()->name)->hiddenOn('edit'),
                        Hidden::make('updated_by')->hiddenOn('create'),

                    ])->columnSpanFull()->columns(4),
                Section::make('INFORMACION PRINCIPAL')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('name_corporative')
                            ->label('Razon Social')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('name', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->validationMessages([
                                'required' => 'Campo requerido',
                            ])
                            ->maxLength(255),
                        TextInput::make('rif')
                            ->label('Rif')
                            ->prefix('J-')
                            ->numeric()
                            ->unique(
                                table: Agency::class,
                                column: 'rif'
                            )
                            ->validationMessages([
                                'required'  => 'Campo requerido',
                                'numeric'   => 'El campo es numerico',
                                'unique'    => 'El rif ya se encuentra registrado en la tabla de agencias. Por favor intente con otro',
                            ]),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->unique(
                                table: Agency::class,
                                column: 'email'
                            )
                            ->validationMessages([
                                'required'  => 'Campo requerido',
                                'email'     => 'El campo es un email',
                                'unique'    => 'El email ya se encuentra registrado en la tabla de agencias. Por favor intente con otro',
                            ])
                            ->maxLength(255),
                        TextInput::make('name_representative')
                            ->label('Nombre del Representante')
                            ->prefixIcon('heroicon-s-identification')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->maxLength(255)
                            ->afterStateUpdatedJs(<<<'JS'
                                            $set('name_representative', $state.toUpperCase());
                                        JS),
                        TextInput::make('ci_responsable')
                            ->label('Cédula del Representante')
                            ->prefix('V-')
                            ->numeric()
                            ->unique(
                                ignoreRecord: true,
                                table: 'agencies',
                                column: 'ci_responsable',
                            )
                            ->validationMessages([
                                'unique'    => 'La cedula del responsable ya se encuentra registrado.',
                                'required'  => 'Campo requerido',
                                'numeric'   => 'El campo es numerico',
                            ]),
                        DatePicker::make('brithday_date')
                            ->label('Fecha de Nacimiento del Representante')
                            ->format('d/m/Y')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ]),
                        DatePicker::make('anniversary_date')
                            ->label('Fecha de Aniversario de la Agencia')
                            ->format('d/m/Y')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ]),
                        TextInput::make('address')
                            ->label('Dirección')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('address', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->maxLength(255),
                        Select::make('country_code')
                            ->label('Código de país')
                            ->options([
                                '+1'   => '🇺🇸 +1 (Estados Unidos)',
                                '+44'  => '🇬🇧 +44 (Reino Unido)',
                                '+49'  => '🇩🇪 +49 (Alemania)',
                                '+33'  => '🇫🇷 +33 (Francia)',
                                '+34'  => '🇪🇸 +34 (España)',
                                '+39'  => '🇮🇹 +39 (Italia)',
                                '+7'   => '🇷🇺 +7 (Rusia)',
                                '+55'  => '🇧🇷 +55 (Brasil)',
                                '+91'  => '🇮🇳 +91 (India)',
                                '+86'  => '🇨🇳 +86 (China)',
                                '+81'  => '🇯🇵 +81 (Japón)',
                                '+82'  => '🇰🇷 +82 (Corea del Sur)',
                                '+52'  => '🇲🇽 +52 (México)',
                                '+58'  => '🇻🇪 +58 (Venezuela)',
                                '+57'  => '🇨🇴 +57 (Colombia)',
                                '+54'  => '🇦🇷 +54 (Argentina)',
                                '+56'  => '🇨🇱 +56 (Chile)',
                                '+51'  => '🇵🇪 +51 (Perú)',
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
                                '+20'  => '🇪🇬 +20 (Egipto)',
                                '+27'  => '🇿🇦 +27 (Sudáfrica)',
                                '+234' => '🇳🇬 +234 (Nigeria)',
                                '+212' => '🇲🇦 +212 (Marruecos)',
                                '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                '+92'  => '🇵🇰 +92 (Pakistán)',
                                '+880' => '🇧🇩 +880 (Bangladesh)',
                                '+62'  => '🇮🇩 +62 (Indonesia)',
                                '+63'  => '🇵🇭 +63 (Filipinas)',
                                '+66'  => '🇹🇭 +66 (Tailandia)',
                                '+60'  => '🇲🇾 +60 (Malasia)',
                                '+65'  => '🇸🇬 +65 (Singapur)',
                                '+61'  => '🇦🇺 +61 (Australia)',
                                '+64'  => '🇳🇿 +64 (Nueva Zelanda)',
                                '+90'  => '🇹🇷 +90 (Turquía)',
                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                '+372' => '🇪🇪 +372 (Estonia)',
                                '+371' => '🇱🇻 +371 (Letonia)',
                                '+370' => '🇱🇹 +370 (Lituania)',
                                '+48'  => '🇵🇱 +48 (Polonia)',
                                '+40'  => '🇷🇴 +40 (Rumania)',
                                '+46'  => '🇸🇪 +46 (Suecia)',
                                '+47'  => '🇳🇴 +47 (Noruega)',
                                '+45'  => '🇩🇰 +45 (Dinamarca)',
                                '+41'  => '🇨🇭 +41 (Suiza)',
                                '+43'  => '🇦🇹 +43 (Austria)',
                                '+31'  => '🇳🇱 +31 (Países Bajos)',
                                '+32'  => '🇧🇪 +32 (Bélgica)',
                                '+353' => '🇮🇪 +353 (Irlanda)',
                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                '+380' => '🇺🇦 +380 (Ucrania)',
                                '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                '+995' => '🇬🇪 +995 (Georgia)',
                                '+976' => '🇲🇳 +976 (Mongolia)',
                                '+998' => '🇺🇿 +998 (Uzbekistán)',
                                '+84'  => '🇻🇳 +84 (Vietnam)',
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
                                'required'  => 'Campo Requerido',
                            ])
                            ->hiddenOn('edit'),
                        TextInput::make('phone')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                $countryCode = $get('country_code');
                                if ($countryCode) {
                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                    $set('phone', $countryCode . $cleanNumber);
                                }
                            }),
                        Select::make('country_id')
                            ->label('País')
                            ->live()
                            ->options(Country::all()->pluck('name', 'id'))
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                        Select::make('state_id')
                            ->label('Estado')
                            ->options(function (Get $get) {
                                return State::where('country_id', $get('country_id'))->pluck('definition', 'id');
                            })
                            ->afterStateUpdated(function (Set $set, $state) {
                                $region_id = State::where('id', $state)->value('region_id');
                                $region = Region::where('id', $region_id)->value('definition');
                                $set('region', $region);
                            })
                            ->live()
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                        TextInput::make('region')
                            ->label('Región')
                            ->prefixIcon('heroicon-m-map')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        Select::make('city_id')
                            ->label('Ciudad')
                            ->options(function (Get $get) {
                                return City::where('country_id', $get('country_id'))->where('state_id', $get('state_id'))->pluck('definition', 'id');
                            })
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                        TextInput::make('user_instagram')
                            ->label('Usuario de Instagram')
                            ->prefixIcon('heroicon-s-user')
                            ->maxLength(255),
                    ])->columnSpanFull()->columns(4),
                Section::make('INFORMACION DE CONTACTO SECUNDARIO')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('name_contact_2')
                            ->label('Nombre/Razon Social')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('name_contact_2', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('email_contact_2')
                            ->label('Email secundario')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->validationMessages([
                                'email'  => 'Campo formato email',
                            ])
                            ->maxLength(255),
                        Select::make('country_code_2')
                            ->label('Código de país')
                            ->options([
                                '+1'   => '🇺🇸 +1 (Estados Unidos)',
                                '+44'  => '🇬🇧 +44 (Reino Unido)',
                                '+49'  => '🇩🇪 +49 (Alemania)',
                                '+33'  => '🇫🇷 +33 (Francia)',
                                '+34'  => '🇪🇸 +34 (España)',
                                '+39'  => '🇮🇹 +39 (Italia)',
                                '+7'   => '🇷🇺 +7 (Rusia)',
                                '+55'  => '🇧🇷 +55 (Brasil)',
                                '+91'  => '🇮🇳 +91 (India)',
                                '+86'  => '🇨🇳 +86 (China)',
                                '+81'  => '🇯🇵 +81 (Japón)',
                                '+82'  => '🇰🇷 +82 (Corea del Sur)',
                                '+52'  => '🇲🇽 +52 (México)',
                                '+58'  => '🇻🇪 +58 (Venezuela)',
                                '+57'  => '🇨🇴 +57 (Colombia)',
                                '+54'  => '🇦🇷 +54 (Argentina)',
                                '+56'  => '🇨🇱 +56 (Chile)',
                                '+51'  => '🇵🇪 +51 (Perú)',
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
                                '+20'  => '🇪🇬 +20 (Egipto)',
                                '+27'  => '🇿🇦 +27 (Sudáfrica)',
                                '+234' => '🇳🇬 +234 (Nigeria)',
                                '+212' => '🇲🇦 +212 (Marruecos)',
                                '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                '+92'  => '🇵🇰 +92 (Pakistán)',
                                '+880' => '🇧🇩 +880 (Bangladesh)',
                                '+62'  => '🇮🇩 +62 (Indonesia)',
                                '+63'  => '🇵🇭 +63 (Filipinas)',
                                '+66'  => '🇹🇭 +66 (Tailandia)',
                                '+60'  => '🇲🇾 +60 (Malasia)',
                                '+65'  => '🇸🇬 +65 (Singapur)',
                                '+61'  => '🇦🇺 +61 (Australia)',
                                '+64'  => '🇳🇿 +64 (Nueva Zelanda)',
                                '+90'  => '🇹🇷 +90 (Turquía)',
                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                '+372' => '🇪🇪 +372 (Estonia)',
                                '+371' => '🇱🇻 +371 (Letonia)',
                                '+370' => '🇱🇹 +370 (Lituania)',
                                '+48'  => '🇵🇱 +48 (Polonia)',
                                '+40'  => '🇷🇴 +40 (Rumania)',
                                '+46'  => '🇸🇪 +46 (Suecia)',
                                '+47'  => '🇳🇴 +47 (Noruega)',
                                '+45'  => '🇩🇰 +45 (Dinamarca)',
                                '+41'  => '🇨🇭 +41 (Suiza)',
                                '+43'  => '🇦🇹 +43 (Austria)',
                                '+31'  => '🇳🇱 +31 (Países Bajos)',
                                '+32'  => '🇧🇪 +32 (Bélgica)',
                                '+353' => '🇮🇪 +353 (Irlanda)',
                                '+375' => '🇧🇾 +375 (Bielorrusia)',
                                '+380' => '🇺🇦 +380 (Ucrania)',
                                '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                '+995' => '🇬🇪 +995 (Georgia)',
                                '+976' => '🇲🇳 +976 (Mongolia)',
                                '+998' => '🇺🇿 +998 (Uzbekistán)',
                                '+84'  => '🇻🇳 +84 (Vietnam)',
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
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
                            ->live(onBlur: true)
                            ->validationMessages([
                                'numeric'   => 'El campo es numerico',
                            ])
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                $countryCode = $get('country_code_2');
                                if ($countryCode) {
                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                    $set('phone_contact_2', $countryCode . $cleanNumber);
                                }
                            }),
                    ])->columnSpanFull()->columns(4),
                Section::make('DATOS BANCARIOS MONEDA NACIONAL')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('local_beneficiary_name')
                            ->label('Nombre/Razón Social del Beneficiario')
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('local_beneficiary_name', $state.toUpperCase());
                            JS)
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('local_beneficiary_rif')
                            ->label('CI/RIF del Beneficiario')
                            ->prefixIcon('heroicon-s-identification')
                            ->validationMessages([
                                'numeric'  => 'Campo tipo numerico',
                            ])
                            ->maxLength(255),
                        TextInput::make('local_beneficiary_phone_pm')
                            ->label('Teléfono Pago Movil del Beneficiario')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->helperText('Formato: 04121234567, 04241869168')
                            ->mask('09999999999'),

                        Fieldset::make('Cuenta Nacional, Moneda Nacional(Bs.)')->schema([
                            TextInput::make('local_beneficiary_account_number')
                                ->label('Número de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification'),
                            Select::make('local_beneficiary_account_bank')
                                ->label('Banco del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->options([
                                    'BANCO DE VENEZUELA'            => 'BANCO DE VENEZUELA',
                                    'BANCO BICENTENARIO'            => 'BANCO BICENTENARIO',
                                    'BANCO MERCANTIL'               => 'BANCO MERCANTIL',
                                    'BANCO PROVINCIAL'              => 'BANCO PROVINCIAL',
                                    'BANCO CARONI'                  => 'BANCO CARONI',
                                    'BANCO DEL CARIBE'              => 'BANCO DEL CARIBE',
                                    'BANCO DEL TESORO'              => 'BANCO DEL TESORO',
                                    'BANCO NACIONAL DE CREDITO'     => 'BANCO NACIONAL DE CREDITO',
                                    'BANESCO'                       => 'BANESCO',
                                    'BANCO CARONI'                  => 'BANCO CARONI',
                                    'FONDO COMUN'                   => 'FONDO COMUN',
                                    'BANCO CANARIAS'                => 'BANCO CANARIAS',
                                    'BANCO DEL SUR'                 => 'BANCO DEL SUR',
                                    'BANCO AGRICOLA DE VENEZUELA'   => 'BANCO AGRICOLA DE VENEZUELA',
                                    'BANPLUS'                       => 'BANPLUS',
                                    'MI BANCO'                      => 'MI BANCO',
                                    'BANCAMIGA'                     => 'BANCAMIGA',
                                    'BANFANB'                       => 'BANFANB',
                                    'BANCARIBE'                     => 'BANCARIBE',
                                    'BANCO ACTIVO'                  => 'BANCO ACTIVO',
                                ]),
                            Select::make('local_beneficiary_account_type')
                                ->label('Tipo de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->options([
                                    'AHORRO'      => 'AHORRO',
                                    'CORRIENTE'   => 'CORRIENTE',
                                ]),
                        ])->columnSpanFull()->columns(3),

                        Fieldset::make('Cuenta Nacional, Moneda Intenacional(US$, EUR)')->schema([
                            TextInput::make('local_beneficiary_account_number_mon_inter')
                                ->label('Número de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification'),
                            Select::make('local_beneficiary_account_bank_mon_inter')
                                ->label('Banco del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->options([
                                    'BANCO DE VENEZUELA'            => 'BANCO DE VENEZUELA',
                                    'BANCO BICENTENARIO'            => 'BANCO BICENTENARIO',
                                    'BANCO MERCANTIL'               => 'BANCO MERCANTIL',
                                    'BANCO PROVINCIAL'              => 'BANCO PROVINCIAL',
                                    'BANCO CARONI'                  => 'BANCO CARONI',
                                    'BANCO DEL CARIBE'              => 'BANCO DEL CARIBE',
                                    'BANCO DEL TESORO'              => 'BANCO DEL TESORO',
                                    'BANCO NACIONAL DE CREDITO'     => 'BANCO NACIONAL DE CREDITO',
                                    'BANESCO'                       => 'BANESCO',
                                    'BANCO CARONI'                  => 'BANCO CARONI',
                                    'FONDO COMUN'                   => 'FONDO COMUN',
                                    'BANCO CANARIAS'                => 'BANCO CANARIAS',
                                    'BANCO DEL SUR'                 => 'BANCO DEL SUR',
                                    'BANCO AGRICOLA DE VENEZUELA'   => 'BANCO AGRICOLA DE VENEZUELA',
                                    'BANPLUS'                       => 'BANPLUS',
                                    'MI BANCO'                      => 'MI BANCO',
                                    'BANCAMIGA'                     => 'BANCAMIGA',
                                    'BANFANB'                       => 'BANFANB',
                                    'BANCARIBE'                     => 'BANCARIBE',
                                    'BANCO ACTIVO'                  => 'BANCO ACTIVO',
                                ]),
                            Select::make('local_beneficiary_account_type_mon_inter')
                                ->label('Tipo de Cuenta del Beneficiario')
                                ->prefixIcon('heroicon-s-identification')
                                ->options([
                                    'AHORRO'      => 'AHORRO',
                                    'CORRIENTE'   => 'CORRIENTE',
                                ]),
                        ])->columnSpanFull()->columns(3),

                    ])->columnSpanFull()->columns(3),
                Section::make('DATOS BANCARIOS MONEDA EXTRANJERA')
                    ->description('Fomulario. Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('extra_beneficiary_name')
                            ->label('Nombre/Razon Social')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('extra_beneficiary_name', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('extra_beneficiary_ci_rif')
                            ->label('Nro. CI/RIF/ID/PASAPORTE')
                            ->prefixIcon('heroicon-s-identification')
                            ->numeric()
                            ->validationMessages([
                                'numeric'  => 'Campo tipo numeric',
                            ])
                            ->maxLength(255),
                        TextInput::make('extra_beneficiary_account_number')
                            ->label('Número de cuenta')
                            ->numeric()
                            ->validationMessages([
                                'numeric'  => 'Campo tipo numerico',
                            ])
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        Select::make('extra_beneficiary_account_bank')
                            ->label('Banco')
                            ->prefixIcon('heroicon-s-identification')
                            ->searchable()
                            ->preload()
                            ->options([
                                'JPMORGAN CHASE & CO'                               => 'JPMORGAN CHASE & CO',
                                'BANK OF AMERICA'                                   => 'BANK OF AMERICA',
                                'WELLS FARGO'                                       => 'WELLS FARGO',
                                'CITIBANK (CITIGROUP)'                              => 'CITIBANK (CITIGROUP)',
                                'U.S. BANK'                                         => 'U.S. BANK',
                                'PNC FINANCIAL SERVICES'                            => 'PNC FINANCIAL SERVICES',
                                'TRUIST FINANCIAL CORPORATION'                      => 'TRUIST FINANCIAL CORPORATION',
                                'CAPITAL ONE'                                       => 'CAPITAL ONE',
                                'TD BANK (TORONTO-DOMINION BANK)'                   => 'TD BANK (TORONTO-DOMINION BANK)',
                                'HSBC BANK USA'                                     => 'HSBC BANK USA',
                                'FIFTH THIRD BANK'                                  => 'FIFTH THIRD BANK',
                                'REGIONS FINANCIAL CORPORATION'                     => 'REGIONS FINANCIAL CORPORATION',
                                'HUNTINGTON NATIONAL BANK'                          => 'HUNTINGTON NATIONAL BANK',
                                'NAVY FEDERAL CREDIT UNION'                         => 'NAVY FEDERAL CREDIT UNION',
                                'STATE EMPLOYEES CREDIT UNION (SECU)'               => 'STATE EMPLOYEES CREDIT UNION (SECU)',
                                'BANCO NACIONAL DE PANAMÁ (BNP)'                    => 'BANCO NACIONAL DE PANAMÁ (BNP)',
                                'CAJA DE AHORROS'                                   => 'CAJA DE AHORROS',
                                'BANCO GENERAL'                                     => 'BANCO GENERAL',
                                'GLOBAL BANK'                                       => 'GLOBAL BANK',
                                'BANESCO PANAMÁ'                                    => 'BANESCO PANAMÁ',
                                'METROBANK'                                         => 'METROBANK',
                                'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)' => 'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)',
                                'HSBC BANK PANAMÁ'                                  => 'HSBC BANK PANAMÁ',
                                'SCOTIABANK PANAMÁ'                                 => 'SCOTIABANK PANAMÁ',
                                'CITIBANK PANAMÁ'                                   => 'CITIBANK PANAMÁ',
                                'BANCO SANTANDER PANAMÁ'                            => 'BANCO SANTANDER PANAMÁ',
                                'BANCO DAVIVIENDA PANAMÁ'                           => 'BANCO DAVIVIENDA PANAMÁ',
                                'BANCO ALIADO'                                      => 'BANCO ALIADO',
                                'MULTIBANK'                                         => 'MULTIBANK',
                                'BANCAMIGA'                                         => 'BANCAMIGA',
                                'BANCO DEL TESORO'                                  => 'BANCO DEL TESORO',
                                'PROVINCIAL'                                        => 'PROVINCIAL',
                            ]),
                        TextInput::make('extra_beneficiary_address')
                            ->label('Direccion')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('extra_beneficiary_address', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        Select::make('extra_beneficiary_account_type')
                            ->label('Banco del Beneficiario')
                            ->prefixIcon('heroicon-s-identification')
                            ->searchable()
                            ->preload()
                            ->options([
                                'CUENTA DE CHEQUES (CHECKING ACCOUNT)'                              => 'CUENTA DE CHEQUES (CHECKING ACCOUNT)',
                                'CUENTA DE AHORROS (SAVINGS ACCOUNT)'                               => 'CUENTA DE AHORROS (SAVINGS ACCOUNT)',
                                'CUENTA CORRIENTE (CURRENT ACCOUNT)'                                => 'CUENTA CORRIENTE (CURRENT ACCOUNT)',
                                'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)'     => 'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)',
                                'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)'                             => 'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)',
                                'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)'                          => 'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)',
                                'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)' => 'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)',
                                'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)'           => 'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)',
                                'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)'           => 'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)',
                                'CUENTA CONJUNTA (JOINT ACCOUNT)'                                   => 'CUENTA CONJUNTA (JOINT ACCOUNT)',
                                'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)'      => 'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)',
                                'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)'                           => 'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)',
                                'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)'                             => 'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)',
                            ]),
                        TextInput::make('extra_beneficiary_route')
                            ->label('Ruta')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('extra_beneficiary_route', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('extra_beneficiary_swift')
                            ->label('Swift')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('extra_beneficiary_swift', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('extra_beneficiary_zelle')
                            ->label('Zelle')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('extra_beneficiary_zelle', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),


                    ])->columnSpanFull()->columns(4),
                Section::make('COMISIONES')
                    ->collapsed()
                    ->description('Fomulario. Campo Requerido(*)')
                    ->icon('heroicon-m-chart-pie')
                    ->schema([
                        Toggle::make('tdec')
                            ->label('TDEC'),
                        Toggle::make('tdev')
                            ->label('TDEV'),
                        TextInput::make('commission_tdec')
                            ->label('Comisión TDEC US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdec_renewal')
                            ->label('Comisión Renovacion TDEC US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdev')
                            ->label('Comisión TDEV US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                        TextInput::make('commission_tdev_renewal')
                            ->label('Comisión Renovacion TDEV US$')
                            ->helperText('Valor expresado en porcentaje. Utilice separador decimal(.)')
                            ->prefix('%')
                            ->numeric()
                            ->validationMessages([
                                'numeric'   => 'Campo tipo numerico.',
                            ]),
                    ])->columnSpanFull()->columns(2),
                // Section::make('COMENTARIOS')
                //     ->collapsed()
                //     ->icon('heroicon-m-folder-plus')
                //     ->schema([
                //         Textarea::make('comments')
                //             ->label('Comentarios')
                //     ])->columnSpanFull(),
                Section::make('OBSERVACIONES')
                    ->description('Seccion para que el analista documente todo lo relacionado a reunion y contactos con la agencia')
                    ->icon('heroicon-m-folder-plus')
                    ->schema([
                        Repeater::make('observationCommercialStructures')
                            ->label('Observaciones')
                            ->relationship()
                            ->table([
                                TableColumn::make('Observacion/Notas'),
                                TableColumn::make('Responsable del Registro'),
                                TableColumn::make('Fecha del Registro'),
                            ])
                            ->schema([
                                Textarea::make('observation')
                                    ->label('Observacion')
                                    ->autosize(),
                                TextInput::make('created_by')
                                    ->label('Responsable')
                                    ->default(Auth::user()->name)
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('date')
                                    ->default(now()->format('d/m/Y H:i:s'))
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->deletable(function () {
                                $user = auth()->user()->departament;
                                if (in_array('SUPERADMIN', $user)) {
                                    return true;
                                }
                                return false;
                            })
                            ->columns(2)
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }
}

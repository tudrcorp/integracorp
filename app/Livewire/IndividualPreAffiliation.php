<?php

namespace App\Livewire;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Plan;
use App\Models\User;
use App\Models\Agent;
use App\Models\State;
use Pages\EditAgency;
use App\Models\Agency;
use App\Models\Region;
use App\Models\Country;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\Affiliate;
use App\Models\AgentType;
use App\Models\TypeAgent;
use App\Models\Affiliates;
use App\Models\Affiliation;
use Filament\MarkdownEditor;
use App\Models\IndividualQuote;
use Awcodes\TableRepeater\Header;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Grid;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Crypt;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Http\Controllers\AgentController;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Awcodes\TableRepeater\Components\TableRepeater;

class IndividualPreAffiliation extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public $id;

    public function mount($id): void
    {
        $this->id = $id;
        $this->form->fill();
    }

    public function form(Form $form): Form
    {

        return $form
            ->schema([
                Section::make('AFILIACION')
                    ->collapsible()
                    ->description('Fomulario de afiliacion. Campo Requerido(*)')
                    ->icon('heroicon-s-building-library')
                    ->schema([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->live()
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->options(function () {
                                $individual_quote_id = Crypt::decryptString($this->id);
                                $plans = DetailIndividualQuote::join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                                    ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
                                    ->where('individual_quotes.id', $individual_quote_id)
                                    ->select('plans.id as plan_id', 'plans.description as description')
                                    ->distinct() // Asegurarse de que no haya duplicados
                                    ->get()
                                    ->pluck('description', 'plan_id');

                                return $plans;
                            }),
                        TextInput::make('date_today')
                            ->label('Fecha')
                            ->default(now()->format('d-m-Y'))
                            ->maxLength(255),
                    ])->columns(4),
                Section::make('INFORMACION PRINCIPAL DEL CONTRATANTE')
                    ->description('Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        TextInput::make('full_name_con')
                            ->label('Nombre y Apellido')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('full_name_con', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->required()
                            ->validationMessages([
                                'required' => 'Campo requerido',
                            ])
                            ->maxLength(255),
                        TextInput::make('nro_identificacion_con')
                            ->label('Nro. de identidad')
                            ->prefix('V/E/C')
                            ->numeric()
                            ->unique(
                                ignoreRecord: true,
                                table: 'affiliations',
                                column: 'nro_identificacion_con',
                            )
                            ->required()
                            ->validationMessages([
                                'unique'    => 'El RIF ya se encuentra registrado.',
                                'required'  => 'Campo requerido',
                                'numeric'   => 'El campo es numerico',
                            ])
                            ->required(),

                        Select::make('sex_con')
                            ->label('Sexo')
                            ->live()
                            ->options([
                                'MASCULINO' => 'MASCULINO',
                                'FEMENINO' => 'FEMENINO',
                            ])
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),

                        DatePicker::make('birth_date_con')
                            ->label('Fecha de Nacimiento')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ]),
                        TextInput::make('email_con')
                            ->label('Email')
                            ->prefixIcon('heroicon-s-at-symbol')
                            ->email()
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                table: 'affiliations',
                                column: 'email_con',
                            )
                            ->validationMessages([
                                'unique'    => 'El Email Corporativo ya se encuentra registrado.',
                                'required'  => 'Campo requerido',
                                'email'     => 'El campo es un email',
                            ])
                            ->maxLength(255),
                        TextInput::make('adress_con')
                            ->label('Dirección')
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('adress_con', strtoupper($state));
                            })
                            ->live(onBlur: true)
                            ->prefixIcon('heroicon-s-identification')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->maxLength(255),
                        Select::make('country_code_con')
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
                            ->live(onBlur: true),
                        TextInput::make('phone_con')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->label('Número de teléfono')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                $countryCode = $get('country_code_con');
                                if ($countryCode) {
                                    $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                    $set('phone_con', $countryCode . $cleanNumber);
                                }
                            }),
                        Select::make('country_id_con')
                            ->label('País')
                            ->live()
                            ->options(Country::all()->pluck('name', 'id'))
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                        Select::make('state_id_con')
                            ->label('Estado')
                            ->options(function (Get $get) {
                                return State::where('country_id', $get('country_id_con'))->pluck('definition', 'id');
                            })
                            ->afterStateUpdated(function (Set $set, $state) {
                                $region_id = State::where('id', $state)->value('region_id');
                                $region = Region::where('id', $region_id)->value('definition');
                                $set('region_con', $region);
                            })
                            ->live()
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                        TextInput::make('region_con')
                            ->label('Región')
                            ->prefixIcon('heroicon-m-map')
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        Select::make('city_id_con')
                            ->label('Ciudad')
                            ->options(function (Get $get) {
                                return City::where('country_id', $get('country_id_con'))->where('state_id', $get('state_id_con'))->pluck('definition', 'id');
                            })
                            ->searchable()
                            ->prefixIcon('heroicon-s-globe-europe-africa')
                            ->required()
                            ->validationMessages([
                                'required'  => 'Campo Requerido',
                            ])
                            ->preload(),
                    ])->columns(4),
                Section::make('INFORMACION PRINCIPAL DEL TITULAR')
                    ->description('Campo Requerido(*)')
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        Grid::make(2)
                        ->schema([
                            Radio::make('feedback')
                                ->label('Si el CONTRATANTE es el mismo TITULAR Indicar:')
                                ->default(true)
                                ->live()
                                ->boolean()
                                ->inline()
                                ->inlineLabel(false)
                        ]),
                        Grid::make(4)
                        ->schema([
                            TextInput::make('full_name_ti')
                                ->label('Nombre y Apellido')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('full_name_ti', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->validationMessages([
                                    'required' => 'Campo requerido',
                                ])
                                ->maxLength(255),
                            TextInput::make('nro_identificacion_ti')
                                ->label('Nro. de identidad')
                                ->prefix('V/E/C')
                                ->numeric()
                                ->unique(
                                    ignoreRecord: true,
                                    table: 'affiliations',
                                    column: 'nro_identificacion_ti',
                                )
                                ->validationMessages([
                                    'numeric'   => 'El campo es numerico',
                                ])
                                ->required(),

                            Select::make('sex_ti')
                                ->label('Sexo')
                                ->live()
                                ->options([
                                    'MASCULINO' => 'MASCULINO',
                                    'FEMENINO' => 'FEMENINO',
                                ])
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),

                            DatePicker::make('birth_date_ti')
                                ->label('Fecha de Nacimiento')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->displayFormat('d/m/Y'),
                            TextInput::make('email_ti')
                                ->label('Email')
                                ->prefixIcon('heroicon-s-at-symbol')
                                ->email()
                                ->unique(
                                    ignoreRecord: true,
                                    table: 'affiliations',
                                    column: 'email_ti',
                                )
                                ->validationMessages([
                                    'unique'    => 'El Email Corporativo ya se encuentra registrado.',
                                    'email'     => 'El campo es un email',
                                ])
                                ->maxLength(255),
                            TextInput::make('adress_ti')
                                ->label('Dirección')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('adress_ti', strtoupper($state));
                                })
                                ->live(onBlur: true)
                                ->prefixIcon('heroicon-s-identification')
                                ->maxLength(255),
                            Select::make('country_code_ti')
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
                                ->live(onBlur: true),
                            TextInput::make('phone_ti')
                                ->prefixIcon('heroicon-s-phone')
                                ->tel()
                                ->label('Número de teléfono')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                    $countryCode = $get('country_code_ti');
                                    if ($countryCode) {
                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                        $set('phone_ti', $countryCode . $cleanNumber);
                                    }
                                }),
                            Select::make('country_id_ti')
                                ->label('País')
                                ->live()
                                ->options(Country::all()->pluck('name', 'id'))
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),
                            Select::make('state_id_ti')
                                ->label('Estado')
                                ->options(function (Get $get) {
                                    return State::where('country_id', $get('country_id_ti'))->pluck('definition', 'id');
                                })
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $region_id = State::where('id', $state)->value('region_id');
                                    $region = Region::where('id', $region_id)->value('definition');
                                    $set('region_ti', $region);
                                })
                                ->live()
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),
                            TextInput::make('region_ti')
                                ->label('Región')
                                ->prefixIcon('heroicon-m-map')
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                            Select::make('city_id_ti')
                                ->label('Ciudad')
                                ->options(function (Get $get) {
                                    return City::where('country_id', $get('country_id_ti'))->where('state_id', $get('state_id_ti'))->pluck('definition', 'id');
                                })
                                ->searchable()
                                ->prefixIcon('heroicon-s-globe-europe-africa')
                                ->preload(),
                        ])->hidden(fn (Get $get) => $get('feedback')),

                    ])->columns(4),
                Section::make('AFILIADOS')
                    ->description('Campo Requerido(*)')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        Repeater::make('affiliates')
                            ->label('afiliados')
                            // ->headers([
                            //     Header::make('Nombre completo'),
                            //     Header::make('C.I.'),
                            //     Header::make('Sexo'),
                            //     Header::make('Fecha de nacimiento'),
                            //     // Header::make('Estatura'),
                            //     // Header::make('Peso'),
                            //     Header::make('Parentesco'),
                            // ])
                            // ->renderHeader(false)
                            // ->showLabels()
                            // ->stackAt(MaxWidth::ExtraSmall)
                            // ->reorderable(false)
                            // ->relationship()
                            ->schema([
                                TextInput::make('full_name')
                                    ->label('Nombre completo')
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $set('full_name', strtoupper($state));
                                    })
                                    ->live(onBlur: true)
                                    ->maxLength(255),
                                TextInput::make('nro_identificacion')
                                    ->label('Nro. de identidad')
                                    ->numeric()
                                    ->unique(
                                        ignoreRecord: true,
                                        table: 'affiliates',
                                        column: 'nro_identificacion',
                                    )
                                    ->validationMessages([
                                        'numeric'   => 'El campo es numerico',
                                    ]),
                                Select::make('sex')
                                    ->label('Sexo')
                                    ->options([
                                        'MASCULINO' => 'MASCULINO',
                                        'FEMENINO' => 'FEMENINO',
                                    ]),
                                DatePicker::make('birth_date')
                                    ->format('d-m-Y'),
                                // TextInput::make('stature')
                                //     ->label('Nro. de personas')
                                //     ->numeric(),
                                // TextInput::make('weight')
                                //     ->label('Nro. de personas')
                                //     ->numeric(),
                                Select::make('relationship')
                                    ->label('Parentesco')
                                    ->options([
                                        'MADRE'     => 'MADRE',
                                        'PADRE'     => 'PADRE',
                                        'ESPOSA'    => 'ESPOSA',
                                        'ESPOSO'    => 'ESPOSO',
                                        'HIJO'      => 'HIJO',
                                        'HIJA'      => 'HIJA',
                                    ]),
                            ])
                            // ->defaultItems(6)
                            ->addActionLabel('Agregar afiliado')
                            ->columns(5)

                    ]),
                Section::make('DECLARACION DE CONDICIONES MEDICAS')
                    ->description('(Sólo para solicitantes del Plan Especial). Responda Si o No, tomando en cuenta todos los solicitantes. Las respuestas afirmativas deben ser ampliadas.')
                    ->collapsed()
                    ->icon('heroicon-s-building-office-2')
                    ->schema([
                        Radio::make('cuestion_1')
                            ->label('¿ Usted y el grupo de beneficiarios solicitantes, gozan de buena salud ?')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_2')
                            ->label('¿ Usted o el grupo de beneficiarios presentan alguna condición médica o congénita?')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_3')
                            ->label('¿ Usted o el grupo de beneficiarios ha sido intervenido quirúrgicamente?')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_4')
                            ->label('Enfermedades Cerebrovasculares, tales como: Desmayos, confusión, parálisis de miembros, dicultad para
                                    hablar, articular y entender, Accidente Cerebro-vascular (ACV). Cefalea o migraña. Epilepsia o Convulsiones.
                                    Otros trastornos o enfermedad del Cerebro o Sistema Nervioso.')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_5')
                            ->label('Enfermedades Respiratorias, tales como: Asma Bronquial, Bronquitis, Bronquiolitis, Enfisema, Neumonía, Enfermedad pulmonar Obstructiva Crónica (EPOC) u otras enfermedades del Sistema Respiratorio.')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_6')
                            ->label('Enfermedades o Trastornos Endocrinos tales como: Diabetes Mellitus, Bocio, hipertiroidismo, hipotiroidismo, Tiroiditis, Resistencia a la insulina, enfermedad de Cushing, cáncer de tiroides.')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_7')
                            ->label('Enfermedades Gastrointestinales como: Litiasis vesicular, Cólico Biliar, Úlcera gástrica, gastritis, Hemorragia
                                    digestivas, colitis, hemorroides, Apendicitis, Peritonitis, Pancreatitis u otros desórdenes del estómago, intestino,
                                    hígado o vesícula biliar.')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_8')
                            ->label('Enfermedades Renales: Litiasis renal, Cólico nefrítico, Sangre en la orina o Hematuria, Cistitis, Infecciones urinarias, Pielonefritis, Insficiencia renal aguda. Otras enfermedades del riñón, vejiga o próstata.')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_9')
                            ->label('Enfermedades Osteoarticulares, Artrosis, Artritis reumatoide, Traumatismo craneoencefálico, Fracturas óseas,
                                    Luxaciones o esguinces, tumores óseos, u otros trastornos de los músculos, articulaciones o columna vertical o
                                    espalda.')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_10')
                            ->label('¿Ha sufrido o padece de alguna enfermedad de la Piel como: Dermatitis, Celulitis, Abscesos cutáneos, quistes, tumores o cáncer? ,Quemaduras o Heridas Complicadas.')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_11')
                            ->label('¿Padece de alguna enfermedad o desorden de los ojos, oídos, nariz o garganta?')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_12')
                            ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o analaxia?')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_13')
                            ->label('¿Usted o alguno de los solicitantes, toma algún tipo de medicamentos por tratamiento prolongado?')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                        Radio::make('cuestion_14')
                            ->label('¿Ha padecido de algún Envenenamiento o Intoxicación, ¿Alergia o Reacción de Hipersensibilidad (medicamentosa, alimentaria, picadura de insecto, otras), edema de glotis o analaxia?')
                            ->default(false)
                            ->live()
                            ->boolean()
                            ->inline()
                            ->inlineLabel(false),
                    
                    ])
                    ->hidden(fn (Get $get) => $get('plan_id') == 1 || $get('plan_id') == 2),
                Section::make('ACUERDO Y CONDICIONES')
                    ->collapsed()
                    ->description(function (Get $get) {
                        if($get('plan_id') == 1 || $get('plan_id') == 2) {
                            return 'Estoy de acuerdo en aceptar la cobertura domiciliaria para patologías agudas del plan seleccionado, bajo los términos y condiciones con que sea
                                       emitido. De no ser así, notificare mi desacuerdo por escrito, durante los quince (15) días siguientes.';
                            
                        }
                        if ($get('plan_id') == 3) {
                            return 'Certifico que he leído todas las respuestas y declaraciones en esta solicitud y que a mi mejor entendimiento, están completas y son verdaderas.
                                    Entiendo que cualquier omisión o declaración incompleta o incorrecta puede causar que las reclamaciones sean negadas y que el plan sea modificado, rescindido
                                    o cancelado.
                                    Estoy de acuerdo en aceptar la cobertura bajo los términos y condiciones con que sea emitida.
                                    De no ser así , notificaré mi desacuerdo por escrito a la compañía durante los quince (15) días siguientes al recibir el certificado de cobertura.
                                    Como Agente, acepto completa responsabilidad por el envío de esta solicitud, todas las primas cobradas y por la entrega de la póliza cuando sea emitida.
                                    Desconozco la existencia de cualquier condición que no haya sido revelada en esta solicitud que pudiera afectar la asegurabilidad de los propuestos asegurados.';
                        }

                    })
                    ->icon('heroicon-m-folder-plus')
                    ->schema([
                        Checkbox::make('is_accepted')
                            ->label('ACEPTO')
                            ->required(),
                        Grid::make(2)->schema([
                            FileUpload::make('vaucher_payment')
                                ->label('Comprobante de pago')
                                ->uploadingMessage('Cargando...')
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios([
                                    '16:9',
                                    '4:3',
                                    '1:1',
                                ]),
                        ]),
                        Grid::make(1)->schema([
                            Textarea::make('observations_payment')
                                ->label('Observaciones del pago')
                                ->autosize()
                        ]),
                        Grid::make(3)->schema([
                            TextInput::make('full_name_agent')
                                ->label('Nombre del agente')
                                ->default(function () {
                                    $individual_quote_id = Crypt::decryptString($this->id);
                                    return User::select('agent_id', 'name')->where('agent_id', IndividualQuote::find($individual_quote_id)->agent_id)->first()->name;
                                })
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                            TextInput::make('code_agent')
                                ->label('Codigo del agente')
                                ->default(function () {
                                    $individual_quote_id = Crypt::decryptString($this->id);
                                    return Agent::select('id', 'code_agent')->where('id', IndividualQuote::find($individual_quote_id)->agent_id)->first()->code_agent;
                                })
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                            TextInput::make('date_today')
                                ->label('Fecha')
                                ->disabled()
                                ->dehydrated()
                                ->default(now()->format('d-m-Y'))
                                ->maxLength(255),
                        ])
                    ])->columns(3),
            ])->statePath('data');
    }

    public function create(): void
    {

        $data = $this->form->getState();

        if ($data['is_accepted'] == false) {
            Notification::make()
                ->title('NOTIFICACION')
                ->body('Debe aceptar el acuerdo y condiciones para poder proseguir con la afiliacion.')
                ->icon('heroicon-m-user-plus')
                ->iconColor('error')
                ->danger()
                ->send();

            return;
        }

        //Infomacion de la cotizacion
        $individual_quote_id = Crypt::decryptString($this->id);
        $individual_quote = IndividualQuote::find($individual_quote_id);

        //Logica para genera el codgio de afiliacion
        if (Affiliation::max('id') == null) {
            $parte_entera = 0;
        } else {
            $parte_entera = Affiliation::max('id');
        }

        $affiliation = Affiliation::create([
            "individual_quote_id"               => $individual_quote_id,
            "code"                              => 'TDEC-AFI-000' . $parte_entera + 1,
            "agent_id"                          => $individual_quote->agent_id,
            "code_agency"                       => $individual_quote->code_agency,
            "plan_id"                           => $data['plan_id'],
            
            'full_name_con'                     => $data['full_name_con'],
            'nro_identificacion_con'            => $data['nro_identificacion_con'],
            'sex_con'                           => $data['sex_con'],
            'birth_date_con'                    => $data['birth_date_con'],
            'adress_con'                        => $data['adress_con'],
            'city_id_con'                       => $data['city_id_con'],
            'state_id_con'                      => $data['state_id_con'],
            'country_id_con'                    => $data['country_id_con'],
            'region_con'                        => $data['region_con'],
            'phone_con'                         => $data['phone_con'],
            'email_con'                         => $data['email_con'],


            'full_name_ti'                     => $data['feedback'] == 1 ? $data['full_name_con'] : $data['full_name_ti'],
            'nro_identificacion_ti'            => $data['feedback'] == 1 ? $data['nro_identificacion_con'] : $data['nro_identificacion_ti'],
            'sex_ti'                           => $data['feedback'] == 1 ? $data['sex_con'] : $data['sex_ti'],
            'birth_date_ti'                    => $data['feedback'] == 1 ? $data['birth_date_con'] : $data['birth_date_ti'],
            'adress_ti'                        => $data['feedback'] == 1 ? $data['adress_con'] : $data['adress_ti'],
            'city_id_ti'                       => $data['feedback'] == 1 ? $data['city_id_con'] : $data['city_id_ti'],
            'state_id_ti'                      => $data['feedback'] == 1 ? $data['state_id_con'] : $data['state_id_ti'],
            'country_id_ti'                    => $data['feedback'] == 1 ? $data['country_id_con'] : $data['country_id_ti'],
            'region_ti'                        => $data['feedback'] == 1 ? $data['region_con'] : $data['region_ti'],
            'phone_ti'                         => $data['feedback'] == 1 ? $data['phone_con'] : $data['phone_ti'],
            'email_ti'                         => $data['feedback'] == 1 ? $data['email_con'] : $data['email_ti'],


            'cuestion_1'                        =>  $data['plan_id'] == 3 ? $data['cuestion_1'] : null,
            'cuestion_2'                        =>  $data['plan_id'] == 3 ? $data['cuestion_2'] : null,
            'cuestion_3'                        =>  $data['plan_id'] == 3 ? $data['cuestion_3'] : null,
            'cuestion_4'                        =>  $data['plan_id'] == 3 ? $data['cuestion_4'] : null,
            'cuestion_5'                        =>  $data['plan_id'] == 3 ? $data['cuestion_5'] : null,
            'cuestion_6'                        =>  $data['plan_id'] == 3 ? $data['cuestion_6'] : null,
            'cuestion_7'                        =>  $data['plan_id'] == 3 ? $data['cuestion_7'] : null,
            'cuestion_8'                        =>  $data['plan_id'] == 3 ? $data['cuestion_8'] : null,
            'cuestion_9'                        =>  $data['plan_id'] == 3 ? $data['cuestion_9'] : null,
            'cuestion_10'                       =>  $data['plan_id'] == 3 ? $data['cuestion_10'] : null,
            'cuestion_11'                       =>  $data['plan_id'] == 3 ? $data['cuestion_11'] : null,
            'cuestion_12'                       =>  $data['plan_id'] == 3 ? $data['cuestion_12'] : null,
            'cuestion_13'                       =>  $data['plan_id'] == 3 ? $data['cuestion_13'] : null,
            'cuestion_14'                       =>  $data['plan_id'] == 3 ? $data['cuestion_14'] : null,

            'full_name_agent'                   => $data['full_name_agent'],
            'code_agent'                        => $data['code_agent'],
            'date_today'                        => $data['date_today'],

            "status"                            => 'APROBADA-POR-PAGAR',
            "created_by"                        => 'LINK EXTERNO',
        ]);

        $data_affiliates = $data['affiliates'];

        

        foreach ($data_affiliates as $value) {
            $affiliates = new Affiliate();
            $affiliates->affiliation_id     = $affiliation->id;
            $affiliates->full_name          = $value['full_name'];
            $affiliates->birth_date         = $value['birth_date'];
            $affiliates->age                = Carbon::createFromFormat('d-m-Y', $value['birth_date'])->age;
            $affiliates->nro_identificacion = $value['nro_identificacion'];
            $affiliates->relationship       = $value['relationship'];
            $affiliates->sex                = $value['sex'];
            $affiliates->save();
            
        }

        //Guardamos los afilidos en la tabla de afiliates
        
        

        Notification::make()
            ->title('AFILIACION INDIVIDUAL REGISTRADA')
            ->body('El registro de la afiliacion fue enviado con exito')
            ->icon('heroicon-m-user-plus')
            ->iconColor('success')
            ->success()
            ->seconds(5)
            ->send();

        //Notificacion para Admin
        $recipient = User::where('is_admin', 1)->get();
        foreach ($recipient as $user) {
            $recipient_for_user = User::find($user->id);
            Notification::make()
                ->title('NUEVA AFILIACION INDIVUDUAL')
                ->body('Se ha registrado una nueva afiliacion individual de forma exitosa')
                ->icon('heroicon-m-user-plus')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($recipient_for_user);
        }

        $this->form->fill();

        //redirect
        $this->redirect('/at/c');
    }

    public function render()
    {
        return view('livewire.individual-pre-affiliation');
    }
}
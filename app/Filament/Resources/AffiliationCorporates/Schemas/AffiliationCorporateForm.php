<?php

namespace App\Filament\Resources\AffiliationCorporates\Schemas;

use App\Models\City;
use App\Models\Plan;
use App\Models\State;
use App\Models\Region;
use App\Models\Country;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AffiliationCorporateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('AFILIACION')
                ->collapsible()
                ->collapsed('edit')
                ->description('Fomulario de afiliacion. Campo Requerido(*)')
                ->icon('heroicon-s-building-library')
                ->schema([
                    Select::make('plan_id')
                        ->label('Plan')
                        ->live()
                        ->prefixIcon('heroicon-m-clipboard-document-check')
                        ->options(function () {
                            $planesConBeneficios = Plan::join('benefit_plans', 'plans.id', '=', 'benefit_plans.plan_id')
                                ->select('plans.id as plan_id', 'plans.description as description')
                                ->distinct() // Asegurarse de que no haya duplicados
                                ->get()
                                ->pluck('description', 'plan_id');

                            return $planesConBeneficios;
                        }),
                    TextInput::make('date_today')
                        ->label('Fecha')
                        ->default(now()->format('d-m-Y'))
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255),
                ])->columns(4),
            Section::make('INFORMACION CORPORATIVA DEL CONTRATANTE')
                ->collapsed('edit')
                ->description('Campo Requerido(*)')
                // ->collapsed()
                ->icon('heroicon-s-building-office-2')
                ->schema([
                    TextInput::make('full_name_con')
                        ->label('Razon social')
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
                    TextInput::make('rif')
                        ->label('Rif:')
                        ->prefix('J-')
                        ->numeric()
                        ->unique(
                            ignoreRecord: true,
                            table: 'affiliation_corporates',
                            column: 'RIF',
                        )
                        ->required()
                        ->validationMessages([
                            'unique'    => 'El RIF ya se encuentra registrado.',
                            'required'  => 'Campo requerido',
                            'numeric'   => 'El campo es numerico',
                        ])
                        ->required(),

                    // DatePicker::make('birth_date_con')
                    //     ->label('Fecha de Nacimiento')
                    //     ->prefixIcon('heroicon-m-calendar-days')
                    //     ->displayFormat('d/m/Y')
                    //     ->required()
                    //     ->validationMessages([
                    //         'required'  => 'Campo Requerido',
                    //     ]),
                    TextInput::make('email_con')
                        ->label('Email')
                        ->prefixIcon('heroicon-s-at-symbol')
                        ->email()
                        ->required()
                        ->unique(
                            ignoreRecord: true,
                            table: 'affiliation_corporates',
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
                        ->hiddenOn('edit')
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
            ]);
    }
}
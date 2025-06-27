<?php

namespace App\Filament\Agents\Resources\CorporateQuotes\Schemas;

use App\Models\Plan;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\AgeRange;
use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class CorporateQuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('COTIZACIÓN CORPORATIVA')
                ->description('Formulario para el registro de cotizaciones corporativas. Campo Requerido(*)')
                ->icon('heroicon-m-tag')
                ->schema([

                    TextInput::make('code')
                        ->label('Código')
                        ->prefixIcon('heroicon-m-clipboard-document-check')
                        ->default(function () {
                            if (CorporateQuote::max('id') == null) {
                                $parte_entera = 0;
                            } else {
                                $parte_entera = CorporateQuote::max('id');
                            }
                            return 'TDEC-CC-000' . $parte_entera + 1;
                        })
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255),
                    TextInput::make('full_name')
                        ->label('Nombre corporativo')
                        ->prefixIcon('heroicon-m-user')
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('full_name', strtoupper($state));
                        })
                        ->live(onBlur: true)
                        ->required()
                        ->validationMessages([
                            'required' => 'Campo requerido',
                        ])
                        ->maxLength(255),
                    TextInput::make('rif')
                        ->label('Rif:')
                        ->numeric()
                        ->prefix('J-')
                        ->required()
                        ->validationMessages([
                            'required' => 'Campo requerido',
                        ])
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Email')
                        ->prefixIcon('heroicon-m-user')
                        ->required()
                        ->validationMessages([
                            'required' => 'Campo requerido',
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
                        ->required()
                        ->live(onBlur: true)
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->hiddenOn('edit'),
                    TextInput::make('phone')
                        ->prefixIcon('heroicon-s-phone')
                        ->tel()
                        ->label('Número de teléfono')
                        ->required()
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
                    Select::make('state_id')
                        ->label('Estado')
                        ->options(function (Get $get) {
                            return State::all()->pluck('definition', 'id');
                        })
                        ->afterStateUpdated(function (Set $set, $state) {
                            $region_id = State::where('id', $state)->value('region_id');
                            $region = Region::where('id', $region_id)->value('definition');
                            $set('region', $region);
                        })
                        ->live()
                        ->searchable()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->required()
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

                    Hidden::make('status')->default('PRE-APROBADA'),
                    Hidden::make('created_by')->default(Auth::user()->name),
                    Hidden::make('agent_id')->default(Auth::user()->agent_id),
                    Hidden::make('code_agency')->default(function () {
                        $code_agency = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;
                        return $code_agency;
                    }),
                    Hidden::make('owner_code')->default(function () {
                        $owner      = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;
                        $jerarquia  = Agency::select('code', 'owner_code')->where('code', $owner)->first()->owner_code;

                        if ($owner == $jerarquia) {
                            return $owner;
                        }

                        if ($owner != $jerarquia) {
                            return $jerarquia;
                        }
                        // return $jerarquia;
                        // return $owner;
                    }),

                    Section::make('PLANES')
                        ->icon('heroicon-s-squares-plus')
                        ->description('Interactividad de seleccion de planes')
                        ->schema([
                            Repeater::make('details_corporate_quote')
                            // TableRepeater::make('details_corporate_quote')
                            //     ->label('Planes a cotizar:')
                            //     ->headers([
                            //         Header::make('Planes'),
                            //         Header::make('Rango de edad'),
                            //         Header::make('Cantidad de personas'),
                            //     ])
                            //     ->renderHeader(false)
                            //     // ->showLabels()
                            //     ->stackAt(MaxWidth::ExtraSmall)
                            //     ->reorderable(false)
                            //     // ->relationship('detailCoporateQuotes')
                                ->schema([
                                    Select::make('plan_id')
                                        ->options(function () {
                                            $planesConBeneficios = Plan::join('benefit_plans', 'plans.id', '=', 'benefit_plans.plan_id')
                                                ->select('plans.id as plan_id', 'plans.description as description')
                                                ->distinct() // Asegurarse de que no haya duplicados
                                                ->get()
                                                ->pluck('description', 'plan_id');

                                            return $planesConBeneficios;
                                        })
                                        ->label('Plan')
                                        ->preload()
                                        ->searchable()
                                        ->live()
                                        ->placeholder('Seleccione un plan'),
                                    Select::make('age_range_id')
                                        ->label('Rango de edad')
                                        ->placeholder('Rango de edad')
                                        ->options(function (Get $get) {
                                            return AgeRange::where('plan_id', $get('plan_id'))->pluck('range', 'id');
                                        })
                                        ->live()
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->disableOptionWhen(function ($value, $state, Get $get) {
                                            return collect($get('../*.age_range_id'))
                                                ->reject(fn($id) => $id == $state)
                                                ->filter()
                                                ->contains($value);
                                        })
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    TextInput::make('total_persons')
                                        ->label('Nro. de personas')
                                        ->placeholder('Nro. de personas ')
                                        ->numeric(),
                                ])
                                ->columns(3)

                        ])->hiddenOn('edit'),

                ])->columnSpanFull(),
            ]);
    }
}
<?php

namespace App\Filament\Resources\CorporateQuoteRequests\RelationManagers;

use BackedEnum;
use App\Models\Plan;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class DetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'details';

    protected static ?string $title = 'PLANES SOLICITADOS';

    protected static string|BackedEnum|null $icon = 'heroicon-s-numbered-list';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('PLANES')
                    ->icon('heroicon-s-squares-plus')
                    ->description('Interactividad de seleccion de planes')
                    ->schema([
                        Repeater::make('details_corporate_quote_requests')
                            ->label('Planes a cotizar:')
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
                                TextInput::make('total_persons')
                                    ->label('Nro. de personas')
                                    ->placeholder('Nro. de personas ')
                                    ->numeric(),
                            ])->columnSpanFull()->columns(3)

                    ])->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('DETALLES DE LA SOLICITUD')
            ->description('Los planes asociados a la solicitud de cotización. Si desea agregar otro plan haz click en el botón "Agregar Plan"')
            ->recordTitleAttribute('corporate_quote_request_id')
            ->columns([
                TextColumn::make('plan.description'),
                TextColumn::make('total_persons')
                    ->label('Nro. de personas')
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color('warning'),

            ])
            ->headerActions([
                /**Crear cotización */
                Action::make('create_corporate_quote')
                    ->label('Crear cotización')
                    ->color('success')
                    ->icon('heroicon-s-check-circle')
                    ->form([
                        Section::make()->schema([
                            Select::make('corporate_quote_request_id')
                                ->default(function () {
                                    $solicitante_id = request()->query('corporate_quote_request_id');
                                    if (isset($solicitante_id)) {
                                        return $solicitante_id;
                                    }
                                    return null;
                                })
                                ->label('Solicitante')
                                ->helperText('Este campo debe ser llenado cuando la cotización debe ser asociada a una solicitud.')
                                ->options(CorporateQuoteRequest::select('id', 'full_name', 'status')->where('status', 'PRE-APROBADA')->pluck('id', 'full_name'))
                                ->relationship(titleAttribute: 'code')
                                ->relationship(
                                    name: 'corporateQuoteRequest',
                                    titleAttribute: 'code',
                                    modifyQueryUsing: fn(Builder $query) => $query->where('status', 'PRE-APROBADA'),
                                )
                                ->getOptionLabelFromRecordUsing(fn(CorporateQuoteRequest $record) => "{$record->code} - {$record->full_name}")
                                ->searchable()
                                ->preload()
                                ->validationMessages([
                                    'required' => 'Campo requerido',
                                ]),
                        ]),

                        Section::make()->schema([
                            TextInput::make('code')
                                ->label('Código')
                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                ->default(function () {
                                    if (CorporateQuote::max('id') == null) {
                                        $parte_entera = 0;
                                    } else {
                                        $parte_entera = CorporateQuote::max('id');
                                    }
                                    return 'COT-CORP-000' . $parte_entera + 1;
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
                                ]),
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
                        ])->columns(2),

                        Hidden::make('status')->default('PRE-APROBADA'),
                        Hidden::make('created_by')->default(Auth::user()->name),
                        /**Codigo del owner de la cotizacion */
                        Hidden::make('owner_code')->default(null),
                    ])
                    ->action(function (RelationManager $livewire, array $data) {

                        $create = UtilsController::createCorporateQuoteWithoutPersons($livewire->getOwnerRecord(), $data);

                        if ($create) {
                            Notification::make()
                                ->title('Cotizacion creada con exito')
                                ->success()
                                ->send();
                        }
                    }),
                CreateAction::make()
                    ->label('Agregar Plan')
                    ->icon('heroicon-s-plus')
                    ->modalHeading(false)
                    ->modalButton('Agregar plan(es)')
                    ->createAnother(false)
                    ->action(function (array $data) {

                        $array = $data['details_corporate_quote_requests'];

                        /**Inicializamos la variable en false para determinar cuando estan enviando planes duplicados */
                        $exists = false;

                        for ($i = 0; $i < count($array); $i++) {
                            if ($this->getOwnerRecord()->details()->where('plan_id', $array[$i]['plan_id'])->exists()) {
                                Notification::make()
                                    ->title('Plan ya existente en la solicitud')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            $this->getOwnerRecord()->details()->create([
                                'plan_id' => $array[$i]['plan_id'],
                                'total_persons' => $array[$i]['total_persons'],
                                'status' => 'PRE-APROBADA'
                            ]);
                        }

                        Notification::make()
                            ->title('Plan Agregado de forma exitosa')
                            ->success()
                            ->send();

                        /**Notificacion por whatsapp */
                    })
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
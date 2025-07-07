<?php

namespace App\Filament\Resources\CorporateQuoteRequests\RelationManagers;

use BackedEnum;
use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\AgeRange;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rules\File;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use App\Models\CorporateQuoteRequestData;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class DetailsDataRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsData';

    protected static ?string $title = 'POBLACIÓN';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-plus';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('corporate_quote_request_id')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Cédula de Identidad')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->suffix(' años')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('condition_medical')
                    ->label('Condición Medica')
                    ->searchable(),
                TextColumn::make('initial_date')
                    ->label('Fecha de Ingreso')
                    ->searchable(),
                TextColumn::make('position_company')
                    ->label('Cargo')
                    ->suffix(' años')
                    ->searchable(),

        ])
            ->filters([
                //
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


                        Section::make()
                            ->heading('Referencias')
                            ->schema([
                                Select::make('code_agency')
                                    ->label('Agencia')
                                    ->options(Agency::select('code')->pluck('code', 'code'))
                                    ->live()
                                    ->helperText('Si el campo vacio se asignará la agencia asociada en la solicitud de cotizacion seleccionada por el usuario.')
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),
                                Select::make('agent_id')
                                    ->label('Agente')
                                    ->options(function (Get $get) {
                                        return Agent::select('owner_code', 'name', 'id')->where('owner_code', $get('code_agency'))->pluck('name', 'id');
                                    })
                                    ->live()
                                    ->helperText('Si el campo vacio se asignará el agente asociado en la solicitud de cotizacion seleccionada por el usuario.')
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),
                            ])->columns(2),

                        Hidden::make('status')->default('PRE-APROBADA'),
                        Hidden::make('created_by')->default(Auth::user()->name),
                        /**Codigo del owner de la cotizacion */
                        Hidden::make('owner_code')->default(null),
                    ])
                    ->action(function (RelationManager $livewire, array $data) {

                        $createCorporateQuote = UtilsController::createCorporateQuote($livewire, $data);

                        if ($createCorporateQuote) {
                            Notification::make()
                                ->title('Cotización creada con exito')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error al crear la cotización')
                                ->danger()
                                ->send();
                        }

                    }),

                /**Calculo de edades */
                Action::make('calculate_ages')
                    ->label('Calcular edades')
                    ->color('azul')
                    ->icon('heroicon-s-check-circle')
                    ->requiresConfirmation()
                    ->action(function (RelationManager $livewire) {

                        try {

                            //Poblacion de la solicitud
                            $data = CorporateQuoteRequestData::where('corporate_quote_request_id', $livewire->ownerRecord->id)->get()->toArray();


                            /**Calculo las edades */
                            for ($i = 0; $i < count($data); $i++) {
                                $data[$i]['age'] = Carbon::createFromFormat('d/m/Y', $data[$i]['birth_date'])->age; //Carbon::parse($data[$i]['birth_date'])->age;
                                CorporateQuoteRequestData::where('id', $data[$i]['id'])->update(['age' => $data[$i]['age']]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Edades calculadas con éxito')
                                ->send();
                        } catch (\Throwable $th) {
                            Log::error('Error al calcular edades: ' . $th->getMessage());
                            Notification::make()
                                ->danger()
                                ->title('Excepción: ')
                                ->body('Error al calcular edades, por favor verificar el archivo de Logs')
                                ->send();
                        }
                    }),


                /**Importar data de poblacion */
                ImportAction::make()
                    ->importer(CorporateQuoteRequestDataImporter::class)
                    ->label('Importar CSV(Población)')
                    ->color('warning')
                    ->icon('heroicon-s-cloud-arrow-up')
                    ->options(function (RelationManager $livewire) {
                        return [
                            'corporate_quote_request_id' => $livewire->ownerRecord->id,
                        ];
                    })
                    ->fileRules([
                        File::types(['csv', 'txt'])->max(1024),
                    ]),

            ])
            ->toolbarActions([
                BulkAction::make('create_quote')
                    ->label('Crear cotización')
                    ->color('verde')
                    ->icon('heroicon-s-check-circle')
                    ->requiresConfirmation()
                    ->action(function (Collection $records, RelationManager $livewire) {
                        /**
                         * Array para el detalle de la solicutud
                         * Con ente array obtenemos los planes asociados a la solicitud
                         */
                        $details = CorporateQuoteRequest::find($livewire->ownerRecord->id);
                        $details_plan = $details->details->toArray();

                        //Poblacion
                        $poblacion = CorporateQuoteRequestData::where('corporate_quote_request_id', $livewire->ownerRecord->id)->get()->toArray();

                        $array = [];

                        for ($i = 0; $i < count($details_plan); $i++) {
                            //Rabgo de edades segun el plan
                            $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details_plan[$i]['plan_id'])->orderBy('range')->get();
                            foreach ($poblacion as $persona) {
                                $edad = (int) $persona['age'];
                                foreach ($rangos as $rango) {
                                    if ($edad >= $rango->age_init && $edad <= $rango->age_end) {
                                        array_push($array, [
                                            'id' => $persona['id'],
                                            'age' => $persona['age'],
                                            'plan_id' => $details_plan[$i]['plan_id'],
                                            'age_range_id' => $rango->id,
                                            'range' => $rango->range,
                                        ]);
                                        break;
                                    }
                                }
                            }
                        }

                        $resultado = collect($array)
                            ->groupBy('plan_id')
                            ->flatMap(function ($grupoPorPlan, $planId) {
                                return $grupoPorPlan->groupBy('age_range_id')->map(fn($subgrupo, $rangoId) => [
                                    'plan_id' => $planId,
                                    'age_range_id' => $rangoId,
                                    'total_persons' => $subgrupo->count(),
                                ])->values();
                            })
                            ->values()
                            ->toArray();

                        dd($resultado);

                        /**
                         * For para realizar el guardado en la tabla de detalle de cotizacion
                         * ----------------------------------------------------------------------------------------------------
                         */
                        for ($i = 1; $i <= count($res_array); $i++) {
                            //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                            $plan_ageRange = AgeRange::where('plan_id', $res_array[$i])
                                ->where('id', $array_details[$i]['age_range_id'])
                                ->with('fees')
                                ->get()
                                ->toArray();

                            for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                                $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();

                                $detail_individual_quote = new DetailCorporateQuote();
                                $detail_individual_quote->corporate_quote_id   = $array_form['id'];
                                $detail_individual_quote->plan_id               = $array_details[$i]['plan_id'];
                                $detail_individual_quote->age_range_id          = $array_details[$i]['age_range_id'];
                                $detail_individual_quote->coverage_id           = $fee->coverage_id;
                                $detail_individual_quote->fee                   = $fee->price;
                                $detail_individual_quote->total_persons         = $array_details[$i]['total_persons'];
                                $detail_individual_quote->subtotal_anual        = $array_details[$i]['total_persons'] * $fee->price;
                                $detail_individual_quote->subtotal_quarterly    = ($array_details[$i]['total_persons'] * $fee->price) / 4;
                                $detail_individual_quote->subtotal_biannual     = ($array_details[$i]['total_persons'] * $fee->price) / 2;
                                $detail_individual_quote->subtotal_monthly      = ($array_details[$i]['total_persons'] * $fee->price) / 12;
                                $detail_individual_quote->status                = 'PRE-APROBADA';
                                $detail_individual_quote->created_by            = Auth::user()->name;
                                $detail_individual_quote->save();
                            }
                        }
                    }),
                DeleteBulkAction::make(),
            ]);
    }
}
<?php

namespace App\Filament\Master\Resources\Affiliations\Tables;

use App\Models\Log;
use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use Filament\Tables\Table;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Http\Controllers\AffiliationController;
use App\Jobs\ResendMailNotificacionAfiliacionIndividual;
use App\Filament\Resources\Affiliations\AffiliationResource;

class AffiliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->query(Affiliation::query()->whereIn('owner_code', [Auth::user()->code_agency, 'TDG-100']))
            ->defaultSort('created_at', 'desc')
            ->description('Lista de cotizaciones generadas por el agente')
            ->columns([
                TextColumn::make('code_agency')
                    ->label('Agencia')
                    ->prefix(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->code_agency)
                            ->with('typeAgency')
                            ->first();

                        return isset($agency_type) ? $agency_type->typeAgency->definition . ' - ' : 'MASTER - ';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable(),
                TextColumn::make('individual_quote.code')
                    ->label('Nro. de cotización')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Código de afiliación')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('registrated_by')
                    ->label('Registrado por:')
                    ->default(function ($record) {
                        if ($record->agent_id == null) {
                            return $record->code_agency;
                        }
                        if ($record->agent_id != null) {
                            if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                                return 'SUB-AGT-000' . $record->agent_id;
                            }
                            return 'AGT-000' . $record->agent_id;
                        }
                    })
                    ->badge()
                    ->icon(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->code_agency)
                            ->with('typeAgency')
                            ->first();
                        if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                            return 'heroicon-m-users';
                        } elseif (Agent::where('id', $record->agent_id)->where('agent_type_id', 2)->exists()) {
                            return 'heroicon-m-user';
                        } elseif ($agency_type->typeAgency->definition == 'MASTER') {
                            return 'heroicon-m-academic-cap';
                        } else {
                            return 'heroicon-s-building-library';
                        }
                    })
                    ->color('azul')
                    ->searchable(),

                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('full_name_con')
                    ->label('Nombre contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('nro_identificacion_con')
                    ->label('CI. contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('sex_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birth_date_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('adress_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email_con')
                    ->label('Email contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('phone_con')
                    ->label('Telefono contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('full_name_ti')
                    ->label('Nombre titular')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('nro_identificacion_ti')
                    ->label('CI. titular')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('sex_ti')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birth_date_ti')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('adress_ti')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city_id_ti')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state_id_ti')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_id_ti')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region_ti')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone_ti')
                    ->label('Telefono titular')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('email_ti')
                    ->label('Email titular')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                IconColumn::make('cuestion_1')
                    ->label('Prgunta 1')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_2')
                    ->label('Prgunta 2')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_3')
                    ->label('Prgunta 3')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_4')
                    ->label('Prgunta 4')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_5')
                    ->label('Prgunta 5')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_6')
                    ->label('Prgunta 6')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_7')
                    ->label('Prgunta 7')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_8')
                    ->label('Prgunta 8')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_9')
                    ->label('Prgunta 9')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_10')
                    ->label('Prgunta 10')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_11')
                    ->label('Prgunta 11')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_12')
                    ->label('Prgunta 12')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_13')
                    ->label('Prgunta 13')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_14')
                    ->label('Prgunta 14')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('date_today')
                    ->label('Fecha')
                    // ->dateTime()
                    ->searchable(),

                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->label('Covertura')
                    ->searchable(),
                TextColumn::make('family_members')
                    ->label('Miembros familiares')
                    ->searchable(),
                TextColumn::make('activated_at')
                    ->label('Activado el:')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estatus')

                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'success',
                            'ACTIVA'                => 'success',
                            'PENDIENTE'             => 'warning',
                            'EXCLUIDO'              => 'danger',
                        };
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
            ActionGroup::make([

                /**UPLOAD */
                Action::make('upload')
                    ->label('Cargar comprobante')
                    ->color('azul')
                    ->icon('heroicon-s-cloud-arrow-up')
                    ->form([
                        /** INFORMACION PRINCIPAL */
                        Fieldset::make('INFORMACION PRINCIPAL')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('plan_id')
                                        ->label('Plan(es) cotizados')
                                        ->live()
                                        ->options(function (Affiliation $record) {
                                            $plans = DetailIndividualQuote::join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                                                ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
                                                ->where('individual_quotes.id', $record->individual_quote_id)
                                                ->select('plans.id as plan_id', 'plans.description as description')
                                                ->distinct() // Asegurarse de que no haya duplicados
                                                ->get()
                                                ->pluck('description', 'plan_id');

                                            return $plans;
                                            // Log::info($record);

                                        })
                                        ->searchable()
                                        ->live()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    Select::make('coverage_id')
                                        ->label('Cobertura(s) cotizadas')
                                        ->live()
                                        ->options(function (Affiliation $record, Get $get) {
                                            $coverages = DetailIndividualQuote::join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                                                ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
                                                ->where('individual_quotes.id', $record->individual_quote_id)
                                                ->where('detail_individual_quotes.plan_id', $get('plan_id'))
                                                ->select('coverages.id as coverage_id', 'coverages.price as description')
                                                ->distinct() // Asegurarse de que no haya duplicados
                                                ->get()
                                                ->pluck('description', 'coverage_id');

                                            return $coverages;
                                        })
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->preload(),

                                    Select::make('payment_frequency')
                                        ->label('Frecuencia de pago')
                                        ->live()
                                        ->options([
                                            'ANUAL'      => 'ANUAL',
                                            'TRIMESTRAL' => 'TRIMESTRAL',
                                            'SEMESTRAL'  => 'SEMESTRAL',
                                            'MENSUAL'    => 'MENSUAL'
                                        ])
                                        ->searchable()
                                        ->live()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload()
                                        ->afterStateUpdated(function ($state, $set, Get $get, Affiliation $record) {
                                            if ($get('payment_frequency') == 'ANUAL') {
                                                //busco el valor de la cotizacion de acuerdo al plan y a la covertura
                                                $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_anual')
                                                    ->where('individual_quote_id', $record->individual_quote_id)
                                                    ->where('plan_id', $get('plan_id'))
                                                    ->where('coverage_id', $get('coverage_id'))
                                                    // ->where('plan_id', $record->plan_id)
                                                    // ->where('coverage_id', $record->coverage_id)
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_anual'));
                                            }
                                            if ($get('payment_frequency') == 'TRIMESTRAL') {

                                                $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_quarterly')
                                                    ->where('individual_quote_id', $record->individual_quote_id)
                                                    ->where('plan_id', $get('plan_id'))
                                                    ->where('coverage_id', $get('coverage_id'))
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_quarterly'));
                                            }
                                            if ($get('payment_frequency') == 'SEMESTRAL') {

                                                $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_biannual')
                                                    ->where('individual_quote_id', $record->individual_quote_id)
                                                    ->where('plan_id', $get('plan_id'))
                                                    ->where('coverage_id', $get('coverage_id'))
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_biannual'));
                                            }
                                            if ($get('payment_frequency') == 'MENSUAL') {

                                                $data_quote = DetailIndividualQuote::select('individual_quote_id', 'plan_id', 'coverage_id', 'subtotal_monthly')
                                                    ->where('individual_quote_id', $record->individual_quote_id)
                                                    ->where('plan_id', $get('plan_id'))
                                                    ->where('coverage_id', $get('coverage_id'))
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_monthly'));
                                            }
                                        }),

                                    TextInput::make('total_amount')
                                        ->label('Total a pagar')
                                        // ->helperText('Punto(.) para separar decimales')
                                        ->prefix('US$')
                                        ->numeric()
                                        ->live(),



                                ]),

                            ]),

                        /**FORMA DE PAGO */
                        Fieldset::make('FORMA DE PAGO')
                            ->schema([

                                /**SELECCION DEL METODO DE PAGO */
                                Grid::make()
                                    ->schema([
                                        Select::make('payment_method')
                                            ->label('Metodo de pago')
                                            ->options([
                                                'EFECTIVO US$'      => 'EFECTIVO US$',
                                                'ZELLE'             => 'ZELLE',
                                                'PAGO MOVIL VES'    => 'PAGO MOVIL(VES)',
                                                'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                                'MULTIPLE'          => 'MULTIPLE'

                                            ])
                                            ->live()
                                            ->required()
                                            ->validationMessages([
                                                'required'  => 'Seleccione un tipo de pago',
                                            ]),
                                    ])->columns(3),

                                /* PAGO EN DOLARES ZELLE */
                                Grid::make(2)->schema([
                                    TextInput::make('reference_payment_zelle')
                                        ->label('Referencia Zelle')
                                        ->helperText('Debe colocar el correo electronico')
                                        ->placeholder('zelle@mail.com')
                                        ->prefix('@:')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Seleccione un tipo de pago',
                                        ]),
                                    Select::make('bank_usd')
                                        ->label('Banco')
                                        ->live()
                                        ->options([
                                            'CHASE BANK'                => 'CHASE BANK',
                                            'BANK OF AMERICA'           => 'BANK OF AMERICA',
                                            'BANESCO, S.A-US$'          => 'BANESCO, S.A - US$',
                                            'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                                            'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                                        ])
                                        ->searchable()
                                        ->live()
                                        ->prefixIcon('heroicon-s-globe-europe-africa'),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_usd')
                                            ->label('Comprobante(US$)')
                                            ->uploadingMessage('Cargando...')
                                            ->image()
                                            ->imageEditor()
                                            ->required()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ]),
                                    ])
                                ])->hidden(function (Get $get) {
                                    if ($get('payment_method') == 'ZELLE') {
                                        return false;
                                    }
                                    return true;
                                }),

                                /** PAGO EN DOLARES EFECTIVO */
                                Grid::make(1)->schema([
                                    FileUpload::make('document_usd')
                                        ->label('Comprobante(US$)')
                                        ->uploadingMessage('Cargando...')
                                        ->image()
                                        ->imageEditor()
                                        ->required()
                                        ->imageEditorAspectRatios([
                                            '16:9',
                                            '4:3',
                                            '1:1',
                                        ]),
                                ])->hidden(function (Get $get) {
                                    if ($get('payment_method') == 'EFECTIVO US$') {
                                        return false;
                                    }
                                    return true;
                                }),

                                /* PAGO EN BOLIVARES */
                                Grid::make(2)->schema([

                                    TextInput::make('tasa_bcv')
                                        ->live(onBlur: true)
                                        ->label('Tasa BCV')
                                        ->placeholder('123.45')
                                        ->helperText('Punto(.) para separar decimales')
                                        ->prefix('US$/VES')
                                        ->numeric()
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo requerido',
                                            'numeric'   => 'El campo es numerico',
                                        ])
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            $set('pay_amount_ves', $state * $get('total_amount'));
                                        }),
                                    TextInput::make('pay_amount_ves')
                                        ->inputMode('numeric') // activa teclado numérico en móvil
                                        ->live()
                                        ->label('Monto a pagar en VES')
                                        ->helperText('Punto(.) para separar decimales')
                                        ->prefix('VES')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(),
                                    Select::make('bank_ves')
                                        ->label('Banco')
                                        ->live()
                                        ->options([
                                            'BANCAMIGA(VES)'           => 'BANCAMIGA',
                                            'BANCO DE VENEZUELA(VES)'  => 'BANCO DE VENEZUELA',
                                        ])
                                        ->searchable()
                                        ->live()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->preload(),
                                    TextInput::make('reference_payment_ves')
                                        ->live()
                                        ->inputMode('numeric') // activa teclado numérico en móvil
                                        ->helperText('Ultimos 6 digitos del comprobante de pago')
                                        ->mask('999999')
                                        ->maxLength(6)
                                        ->rules([
                                            'regex:/^\d{1,6}$/' // Acepta de 1 a 6 dígitos
                                        ])
                                        ->prefix('Ref:'),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_ves')
                                            ->label('Comprobante(VES)')
                                            ->uploadingMessage('Cargando...')
                                            ->image()
                                            ->imageEditor()
                                            ->required()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '4:3',
                                                '1:1',
                                            ]),
                                    ])

                                ])->hidden(function (Get $get) {
                                    if ($get('payment_method') == 'TRANSFERENCIA VES' || $get('payment_method') == 'PAGO MOVIL VES') {
                                        return false;
                                    }
                                    return true;
                                }),

                                /** PAGO MULTIPLE */
                                Grid::make(2)->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('tasa_bcv')
                                            ->live()
                                            ->label('Tasa BCV')
                                            ->placeholder('123.45')
                                            ->helperText('Punto(.) para separar decimales')
                                            ->prefix('VES')
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required'  => 'Campo requerido',
                                                'numeric'   => 'El campo es numerico',
                                            ]),

                                    ]),
                                    Select::make('payment_method_usd')
                                        ->label('Metodo de pago en dolares(US$)')
                                        ->options([
                                            'EFECTIVO US$'      => 'EFECTIVO US$',
                                            'ZELLE'             => 'ZELLE',
                                        ])
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Seleccione un tipo de pago',
                                        ]),

                                    Select::make('payment_method_ves')
                                        ->label('Metodo de pago en bolivares(VES)')
                                        ->options([
                                            'PAGO MOVIL VES'    => 'PAGO MOVIL(VES)',
                                            'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                        ])
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Seleccione un tipo de pago',
                                        ]),

                                    Select::make('bank_usd')
                                        ->label('Banco')
                                        ->live()
                                        ->options([
                                            'CHASE BANK'                => 'CHASE BANK',
                                            'BANK OF AMERICA'           => 'BANK OF AMERICA',
                                            'BANESCO, S.A-US$'          => 'BANESCO, S.A - US$',
                                            'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                                            'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                                        ])
                                        ->searchable()
                                        ->live()
                                        ->prefixIcon('heroicon-s-globe-europe-africa'),

                                    Select::make('bank_ves')
                                        ->label('Banco')
                                        ->live()
                                        ->options([
                                            'BANCAMIGA - VES'           => 'BANCAMIGA - VES',
                                            'BANCO DE VENEZUELA - VES'  => 'BANCO DE VENEZUELA - VES',
                                        ])
                                        ->searchable()
                                        ->live()
                                        ->prefixIcon('heroicon-s-globe-europe-africa'),

                                    TextInput::make('pay_amount_usd')
                                        ->inputMode('numeric') // activa teclado numérico en móvil
                                        ->live(onBlur: true)
                                        ->label('Monto US$:')
                                        ->helperText('Punto(.) para separar decimales')
                                        ->prefix('US$')
                                        ->numeric()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            $res = $get('total_amount') - $state;
                                            Log::info($get('total_amount'));
                                            Log::info($res);
                                            Log::info($res / $get('tasa_bcv'));
                                            $set('pay_amount_ves', $res * $get('tasa_bcv'));
                                        }),
                                    TextInput::make('pay_amount_ves')
                                        ->inputMode('numeric') // activa teclado numérico en móvil
                                        ->live()
                                        ->label('Monto VES:')
                                        ->helperText('Punto(.) para separar decimales')
                                        ->prefix('VES')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('reference_payment_zelle')
                                        ->label('Referencia Zelle')
                                        ->helperText('Debe colocar el correo electronico')
                                        ->placeholder('zelle@mail.com')
                                        ->prefix('@:')
                                        ->validationMessages([
                                            'required'  => 'Seleccione un tipo de pago',
                                        ]),

                                    TextInput::make('reference_payment_ves')
                                        ->live()
                                        ->inputMode('numeric') // activa teclado numérico en móvil
                                        ->helperText('Ultimos 6 digitos del comprobante de pago')
                                        ->mask('999999')
                                        ->maxLength(6)
                                        ->rules([
                                            'regex:/^\d{1,6}$/' // Acepta de 1 a 6 dígitos
                                        ])
                                        ->prefix('Ref:'),

                                    FileUpload::make('document_usd')
                                        ->label('Comprobante(US$)')
                                        ->uploadingMessage('Cargando...')
                                        ->image()
                                        ->imageEditor()
                                        ->imageEditorAspectRatios([
                                            '16:9',
                                            '4:3',
                                            '1:1',
                                        ]),

                                    FileUpload::make('document_ves')
                                        ->label('Comprobante(VES)')
                                        ->uploadingMessage('Cargando...')
                                        ->image()
                                        ->imageEditor()
                                        ->required()
                                        ->imageEditorAspectRatios([
                                            '16:9',
                                            '4:3',
                                            '1:1',
                                        ]),
                                ])->hidden(function (Get $get) {
                                    if ($get('payment_method') == 'MULTIPLE') {
                                        return false;
                                    }
                                    return true;
                                }),

                            ]),

                        /**OBSERVACIONES */
                        Grid::make(1)->schema([
                            Textarea::make('observations_payment')
                                ->label('Observaciones')
                                ->rows(2)
                                ->autosize()
                        ]),
                    ])
                    ->action(function (Affiliation $record, array $data): void {

                        $upload = AffiliationController::uploadPayment($record, $data, 'MASTER');

                        if ($upload) {
                            Notification::make()
                                ->title('NOTIFICACION')
                                ->body('El comprobante de pago se ha registrado con exito')
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
                                    ->title('REGISTRO DE COMPROBANTE')
                                    ->body('Se ha registrado un nuevo comprobante de pago de forma exitosa. Afiliacion Nro. ' . $record->code)
                                    ->icon('heroicon-m-user-plus')
                                    ->iconColor('success')
                                    ->success()
                                    ->actions([
                                        Action::make('view')
                                            ->label('Ver detalle de pago')
                                            ->button()
                                            ->url(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'admin') . '?activeRelationManager=1'),
                                    ])
                                    ->sendToDatabase($recipient_for_user);
                            }
                        }
                    })
                    ->hidden(function (Affiliation $record) {

                        if ($record->payment_frequency == 'ANUAL' && $record->paid_memberships()->count() == 1) {
                            return true;
                        }

                        if ($record->payment_frequency == 'SEMESTRAL' && $record->paid_memberships()->count() == 2) {
                            return true;
                        }

                        if ($record->payment_frequency == 'TRIMESTRAL' && $record->paid_memberships()->count() == 4) {
                            return true;
                        }

                        return false;
                    }),

                /**REENVIAR PROPUESTA */
                Action::make('forward')
                    ->label('Reenviar certificación')
                    ->icon('heroicon-o-arrow-uturn-right')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Reenvío de Certificación')
                    ->modalWidth(Width::ExtraLarge)
                    ->form([
                        Section::make()
                            ->heading('Informacion')
                            ->description('El link puede sera enviado por email y/o telefono!')
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email(),
                                Grid::make(2)->schema([
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
                                ])
                            ])
                    ])
                    ->action(function (Affiliation $record, array $data) {

                        try {

                            $email = null;
                            $phone = null;

                            if (isset($data['email'])) {
                                $email = $data['email'];
                            }

                            if (isset($data['phone'])) {
                                $phone = $data['phone'];
                            }

                            /**
                             * JOB
                             * Este job ejecuta el reenvio del documento seleccionado
                             * 
                             * @param $name_ti
                             * @param $title
                             * @param $name_pdf
                             * @param $email
                             * @param $phone
                             *   
                             */
                            $title = 'CERTIFICADO DE AFILIACION';
                            $name_ti = $record->name_ti;
                            $name_pdf = 'CER-' . $record->code . '.pdf';
                            $email = $data['email'];
                            $phone = $data['phone'];
                            $job = ResendMailNotificacionAfiliacionIndividual::dispatch($email, $phone, $title, $name_ti, $name_pdf);

                            if ($job) {
                                Notification::make()
                                    ->title('RE-ENVIADO EXITOSO')
                                    ->body('La informacion fue re-enviada exitosamente.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('verde')
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $th) {
                            LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.AffiliationResource.action.enit', $th->getMessage());
                            Notification::make()
                                ->title('ERROR')
                                ->body($th->getMessage())
                                ->icon('heroicon-s-x-circle')
                                ->iconColor('danger')
                                ->danger()
                                ->send();
                        }
                    }),

                /**DESCARGAR */
                Action::make('download')
                    ->label('Descargar certificado')
                    ->icon('heroicon-s-arrow-down-on-square-stack')
                    ->color('verde')
                    ->requiresConfirmation()
                    ->modalHeading('DESCARGAR CERTIFICADO')
                    ->modalWidth(Width::ExtraLarge)
                    ->action(function (Affiliation $record, array $data) {

                        try {

                            /**
                             * Descargar el documento asociado a la cotizacion
                             * ruta: storage/
                             */
                            $path = public_path('storage/CER-' . $record->code . '.pdf');
                            return response()->download($path);


                            /**
                             * LOG
                             */
                            LogController::log(Auth::user()->id, 'Descarga de documento', 'Modulo Cotizacion Individual', 'DESCARGAR');
                        } catch (\Throwable $th) {
                            LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                            Notification::make()
                                ->title('ERROR')
                                ->body($th->getMessage())
                                ->icon('heroicon-s-x-circle')
                                ->iconColor('danger')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->icon('heroicon-c-ellipsis-vertical')
                ->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
<?php

namespace App\Filament\Resources\Affiliations\Tables;

use App\Models\User;
use Filament\Tables\Table;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Alignment;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Http\Controllers\AffiliationController;
use App\Filament\Resources\Affiliations\AffiliationResource;

class AffiliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('AFILIACIONES INDIVIDUALES')
            ->description('Lista de afiliaciones individuales registradas en el sistema')
            ->columns([
                TextColumn::make('code')
                    ->label('Codigo')
                    ->icon('heroicon-s-user-group')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('individual_quote.code')
                    ->label('Nro. de cotización')
                    ->badge()
                    ->color('verde')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('code_agency')
                    ->label('CO-Agencia')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('agent.name')
                    ->label('Nombre del agente')
                    ->badge()
                    ->color('azulOscuro')
                    ->icon('heroicon-m-user')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->label('Covertura')
                    ->numeric()
                    ->badge()
                    ->color('azulOscuro')
                    ->suffix(' US$')
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


                /**
                 * INFORMACION ILS
                 * ------------------------------------------------------------
                 */
                TextColumn::make('vaucher_ils')
                    ->label('Voucher ILS')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_payment_initial_ils')
                    ->label('ILS-Desde')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_payment_final_ils')
                    ->label('ILS-Hasta')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('document_ils')
                    ->alignment(Alignment::Center)
                    ->label('Documento ILS')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document_ils
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document_ils
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->document_ils);
                    })
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
                //-------------------------------------------------

                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->searchable(),

                TextColumn::make('family_members')
                    ->label('Poblacion')
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
                    ->searchable()
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'heroicon-c-information-circle',
                            'ACTIVA'                => 'heroicon-s-check-circle',
                            'PENDIENTE'             => 'heroicon-s-exclamation-circle',
                            'EXCLUIDO'              => 'heroicon-c-x-circle',
                        };
                    }),
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
                    Action::make('affiliation_activate')
                        ->label('Activar')
                        ->color('success')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('Activar afiliacion')
                        ->form([
                            Section::make('ACTIVAR AFILIACION')
                                ->description('Foirmulario de activacion de afiliacion. Campo Requerido(*)')
                                ->icon('heroicon-s-check-circle')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('vaucher_ils')
                                            ->label('Vaucher ILS')
                                            ->required(),
                                    ]),
                                    Grid::make(2)->schema([
                                        DatePicker::make('date_payment_initial_ils')
                                            ->label('Desde')
                                            ->format('d-m-Y')
                                            ->required(),
                                        DatePicker::make('date_payment_final_ils')
                                            ->label('Hasta')
                                            ->format('d-m-Y')
                                            ->required(),

                                    ]),
                                    Grid::make(1)->schema([
                                        FileUpload::make('document_ils')
                                            ->label('Documento/Comprobante ILS')
                                            ->required(),
                                    ])
                                ])
                        ])
                        ->action(function (Affiliation $record, array $data): void {

                            $record->update([
                                'vaucher_ils' => $data['vaucher_ils'],
                                'date_payment_initial_ils' => $data['date_payment_initial_ils'],
                                'date_payment_final_ils' => $data['date_payment_final_ils'],
                                'document_ils' => $data['document_ils'],
                                'status' => 'ACTIVA',
                            ]);

                            $record->status_log_affiliations()->create([
                                'affiliation_id'  => $record->id,
                                'action'          => 'ACTIVACIÓN',
                                'observation'     => 'AFILIACIÓN ACTIVADA. FECHA: ' . now()->format('d-m-Y'),
                                'updated_by'      => Auth::user()->name
                            ]);

                            $record->sendTarjetaAfiliacion($record);

                            Notification::make()
                                ->success()
                                ->title('Afiliacion activada')
                                ->send();
                        })
                        ->hidden(function (Affiliation $record): bool {
                            return $record->status !== 'PRE-APROBADA';
                        }),
                    Action::make('upload')
                        ->label('Cargar pago')
                        ->color('verde')
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
                                                ->native(false)
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
                                            ->native(false)
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
                                            ->native(false)
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
                                            ->native(false)
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
                                            ->native(false)
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

                            $upload = AffiliationController::uploadPayment($record, $data, 'AGENTE');

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
                    Action::make('change_status')
                        ->label('Actualizar estatus')
                        ->color('azulOscuro')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ExtraLarge)
                        ->modalHeading('ACCIONES')
                        ->form([
                            Section::make()
                                ->heading('ACCIONES')
                                ->description('Seleccione la accion que desea realizar')
                                ->icon('heroicon-s-check-circle')
                                ->schema([
                                    Grid::make(2)->schema([
                                        Radio::make('action')
                                            ->label('Que accion deseas realizar?')
                                            ->options([
                                                'observation' => 'Anadir observaciones',
                                                'status'      => 'Actualizar estatus',
                                                'exclude'     => 'Excluir Afiliación',
                                            ])
                                            ->live()
                                            ->required()
                                        // ->inline()
                                    ]),

                                    Grid::make(1)->schema([
                                        Textarea::make('description')
                                            ->label('Observaciones')
                                            ->autosize()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            })
                                    ])->hidden(fn(Get $get) => $get('action') != 'observation'),

                                    Grid::make(1)->schema([
                                        Select::make('status')
                                            ->label('Estatus')
                                            ->options([
                                                'PENDIENTE' => 'PENDIENTE',
                                            ])
                                            ->searchable()
                                            ->preload(),
                                        Textarea::make('description')
                                            ->autosize()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            })
                                    ])->hidden(fn(Get $get) => $get('action') != 'status'),

                                    Grid::make(1)->schema([
                                        DatePicker::make('date_egress')
                                            ->label('Fecha de egreso')
                                            ->format('d-m-Y'),
                                        Textarea::make('description')
                                            ->label('Observaciones')
                                            ->autosize()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            })
                                    ])->hidden(fn(Get $get) => $get('action') != 'exclude'),
                                ])
                        ])
                        ->action(function (Affiliation $record, array $data): void {
                            if ($data['action'] == 'observation') {
                                $record->status_log_affiliations()->create([
                                    'affiliation_id'    => $record->id,
                                    'action'            => 'AGREGO OBSERVACION',
                                    'observation'       => $data['description'],
                                    'updated_by'        => Auth::user()->name
                                ]);
                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();
                                return;
                            }

                            if ($data['action'] == 'status') {
                                $record->update([
                                    'status' => $data['status'],
                                ]);
                                $record->status_log_affiliations()->create([
                                    'affiliation_id'    => $record->id,
                                    'action'            => 'CAMBIO ESTATUS A: ' . $data['status'],
                                    'observation'       => $data['description'],
                                    'updated_by'        => Auth::user()->name
                                ]);
                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();
                                return;
                            }

                            if ($data['action'] == 'exclude') {
                                $record->update([
                                    'status' => 'EXCLUIDO',
                                ]);
                                $record->status_log_affiliations()->create([
                                    'affiliation_id'    => $record->id,
                                    'action'            => 'EXCLUYO AFILIACION, FECHA DE EGRESO: ' . $data['date_egress'],
                                    'observation'       => $data['description'],
                                    'updated_by'        => Auth::user()->name
                                ]);
                                Notification::make()
                                    ->title('AFILIACION ACTUALIZADA')
                                    ->success()
                                    ->send();
                                return;
                            }


                            Notification::make()
                                ->title('AFILIACION ACTUALIZADA')
                                ->success()
                                ->send();
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
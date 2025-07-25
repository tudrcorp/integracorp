<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\Tables;

use Carbon\Carbon;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use App\Models\AffiliateCorporate;
use Illuminate\Support\Facades\Log;
use App\Models\AffiliationCorporate;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
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
use App\Http\Controllers\AffiliationCorporateController;
use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;

class AffiliationCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(AffiliationCorporate::query()->where('agent_id', Auth::user()->agent_id))
            ->defaultSort('created_at', 'desc')
            ->heading('AFILIACIONES CORPORATIVAS')
            ->description('Lista de afiliaciones corporativas registradas en el sistema')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('corporate_quote.code')
                    ->label('Nro. de cotización')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'PLAN INICIAL'  => 'azul',
                            'PLAN IDEAL'    => 'azulOscuro',
                            'PLAN ESPECIAL' => 'verde',
                            default => 'secondary',
                        };
                    })
                    ->searchable(),
            TextColumn::make('full_name_con')
                    ->label('Nombre contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                                Grid::make()->schema([
                                    Select::make('plan_id')
                                        ->label('Plan(es) cotizados')
                                        ->live()
                                        ->options(function (AffiliationCorporate $record) {
                                            $plans = DetailCorporateQuote::join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                                                ->join('corporate_quotes', 'detail_corporate_quotes.corporate_quote_id', '=', 'corporate_quotes.id')
                                                ->where('corporate_quotes.id', $record->corporate_quote_id)
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
                                        ->options(function (AffiliationCorporate $record, Get $get) {
                                            $coverages = DetailCorporateQuote::join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                                                ->join('corporate_quotes', 'detail_corporate_quotes.corporate_quote_id', '=', 'corporate_quotes.id')
                                                ->where('corporate_quotes.id', $record->corporate_quote_id)
                                                ->where('detail_corporate_quotes.plan_id', $get('plan_id'))
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
                                        ->afterStateUpdated(function ($state, $set, Get $get, AffiliationCorporate $record) {
                                            if ($get('payment_frequency') == 'ANUAL') {
                                                //busco el valor de la cotizacion de acuerdo al plan y a la covertura
                                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_anual')
                                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                                    ->where('plan_id', $get('plan_id'))
                                                    ->where('coverage_id', $get('coverage_id'))
                                                    // ->where('plan_id', $record->plan_id)
                                                    // ->where('coverage_id', $record->coverage_id)
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_anual'));
                                            }
                                            if ($get('payment_frequency') == 'TRIMESTRAL') {

                                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_quarterly')
                                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                                    ->where('plan_id', $get('plan_id'))
                                                    ->where('coverage_id', $get('coverage_id'))
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_quarterly'));
                                            }
                                            if ($get('payment_frequency') == 'SEMESTRAL') {

                                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_biannual')
                                                    ->where('corporate_quote_id', $record->corporate_quote_id)
                                                    ->where('plan_id', $get('plan_id'))
                                                    ->where('coverage_id', $get('coverage_id'))
                                                    ->get();

                                                $set('total_amount', $data_quote->sum('subtotal_biannual'));
                                            }
                                            if ($get('payment_frequency') == 'MENSUAL') {

                                                $data_quote = DetailCorporateQuote::select('corporate_quote_id', 'plan_id', 'coverage_id', 'subtotal_monthly')
                                                    ->where('corporate_quote_id', $record->corporate_quote_id)
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



                                ])->columnSpanFull(),

                            ])->columnSpanFull(),

                        /**FORMA DE PAGO */
                        Fieldset::make('FORMA DE PAGO')
                            ->schema([

                                /**SELECCION DEL METODO DE PAGO */
                                Grid::make()
                                    ->schema([
                                        Select::make('payment_method')
                                            ->native(false)
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
                                    ])->columnSpan(3),

                                /* PAGO EN DOLARES ZELLE */
                                Grid::make()->schema([
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
                                })->columnSpan(2),

                                /** PAGO EN DOLARES EFECTIVO */
                                Grid::make(1)->schema([
                                    FileUpload::make('document_usd')
                                        ->label('Comprobante(US$)')
                                        ->disk('public')
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
                                        ->native(false)
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
                                            ->disk('public')
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
                                        ->native(false)
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
                                        ->disk('public')
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
                                        ->disk('public')
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
                    ->action(function (AffiliationCorporate $record, array $data): void {

                        $upload = AffiliationCorporateController::uploadPayment($record, $data, 'AGENTE');

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
                                            ->url(AffiliationCorporateResource::getUrl('edit', ['record' => $record->id], panel: 'admin') . '?activeRelationManager=1'),
                                    ])
                                    ->sendToDatabase($recipient_for_user);
                            }
                        }
                    })
                    ->hidden(function (AffiliationCorporate $record) {

                        if ($record->payment_frequency == 'ANUAL' && $record->paid_membership_corporates()->count() == 1) {
                            return true;
                        }

                        if ($record->payment_frequency == 'SEMESTRAL' && $record->paid_membership_corporates()->count() == 2) {
                            return true;
                        }

                        if ($record->payment_frequency == 'TRIMESTRAL' && $record->paid_membership_corporates()->count() == 4) {
                            return true;
                        }

                        return false;
                    }),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->striped();
    }
}
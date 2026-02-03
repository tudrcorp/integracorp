<?php

namespace App\Filament\General\Resources\Affiliations\Tables;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use Filament\Tables\Table;
use App\Mail\UploadPayment;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
use Filament\Tables\Columns\ColumnGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            ->query(Affiliation::query()->where('code_agency', Auth::user()->code_agency))
            ->defaultSort('created_at', 'desc')
            ->heading('Lista de afiliaciones generadas por el agente')
            ->description('Al seleccionar varias afiliaciones, encuentras un acceso rápido para realizar un pago masivo. Haciendo click en el botón "Abrir acciones" se desplegará "Pagar Afiliaciones".')
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
                TextColumn::make('accountManager.name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->default(fn($record): string => $record->accountManager ? $record->accountManager : '-----')
                    ->color(function (string $state): string {
                        return match ($state) {
                            '-----' => 'info',
                            default => 'success',
                        };
                    }),
                TextColumn::make('agency.name_corporative')
                    ->label('CO-Agencia')
                    ->badge()
                    ->default(fn($record): string => $record->code_agency == 'TDG-100' ? 'TUDRENCASA' : '-----')
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('agent.name')
                    ->label('Nombre del agente')
                    ->badge()
                    ->default(fn($record): string => $record->agent_id == null ? '-----' : $record->agent->name)
                    ->color('azulOscuro')
                    ->icon('heroicon-m-user')
                    ->searchable(),

                //...  
                ColumnGroup::make('Plan Afiliado', [
                    TextColumn::make('plan.description')
                        ->label('Plan')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('coverage.price')
                        ->label('Covertura')
                        ->alignCenter()
                        ->numeric()
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->searchable(),
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('family_members')
                        ->label('Poblacion')
                        ->alignCenter()
                        ->suffix(' persona(s)')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('fee_anual')
                        ->label('Tarifa Anual')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->color('warning')
                        ->searchable(),
                    //total_amount
                    TextColumn::make('total_amount')
                        ->label('Total a Pagar')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->color('warning')
                        ->searchable(),
                ]),

                //...
                ColumnGroup::make('Información del Titular', [
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
                        ->label('Sexo')
                        ->searchable(),
                    TextColumn::make('birth_date_ti')
                        ->label('Fecha de nacimiento')
                        ->searchable(),
                    TextColumn::make('phone_ti')
                        ->label('Telefono titular')
                        ->icon('heroicon-m-phone')
                        ->searchable(),
                    TextColumn::make('email_ti')
                        ->label('Email titular')
                        ->icon('fontisto-email')
                        ->searchable(),
                    TextColumn::make('adress_ti')
                        ->label('Direccion')
                        ->icon('fontisto-map-marker-alt')
                        ->searchable(),
                    TextColumn::make('city.definition')
                        ->label('Ciudad')
                        ->searchable(),
                    TextColumn::make('state.definition')
                        ->label('Estado')
                        ->searchable(),
                    TextColumn::make('region_ti')
                        ->label('Region')
                        ->searchable(),
                    TextColumn::make('country.name')
                        ->label('Pais')
                        ->searchable(),
                ]),

                //...
                ColumnGroup::make('Información del Tomador', [
                    TextColumn::make('full_name_payer')
                        ->label('Nombre y Apellido')
                        ->badge()
                        ->alignCenter()
                        ->color('azulOscuro')
                        ->searchable(),
                    TextColumn::make('nro_identificacion_payer')
                        ->label('Numero de Identificación')
                        ->badge()
                        ->alignCenter()
                        ->color('azulOscuro')
                        ->searchable(),
                ]),

                //...
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),

                TextColumn::make('activated_at')
                    ->label('Fecha de Activación')
                    ->color('warning')
                    ->icon('heroicon-s-calendar')
                    ->badge()
                    ->searchable(),

                TextColumn::make('effective_date')
                    ->label('Vigencia')
                    ->color('success')
                    ->icon('heroicon-s-calendar')
                    ->badge()
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
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    /**EDIT */

                    /**UPLOAD */
                    Action::make('upload')
                        ->label('Comprobante de Pago')
                        ->color('azul')
                        ->icon('heroicon-s-cloud-arrow-up')
                        ->modalWidth(Width::FourExtraLarge)
                        ->form([

                            /** INFORMACION PRINCIPAL */
                            Fieldset::make('INFORMACION PRINCIPAL')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('total_amount')
                                            ->label('Total a pagar')
                                            ->helperText(function ($state, $set, Get $get, Affiliation $record) {
                                                // dd($record->coverage_id);
                                                if (isset($record->coverage_id)) {
                                                    return 'Plan: ' . $record->plan->description . ' - Cobertura: ' . $record->coverage->price . ' - Frecuencia: ' . $record->payment_frequency;
                                                }
                                                return 'Plan: ' . $record->plan->description . ' - Frecuencia: ' . $record->payment_frequency;
                                            })
                                            ->prefix('US$')
                                            ->default(function ($state, $set, Get $get, Affiliation $record) {
                                                /**
                                                 * Se modifica la logia para buscar el monto a pagar en la tabla 
                                                 * de afiliaciones y no en la tabla de cotizaciones
                                                 */
                                                $amount = Affiliation::where('id', $record->id)->first();
                                                return $amount->total_amount;
                                            })
                                            ->disabled()
                                            ->dehydrated()
                                            ->numeric()
                                            ->live(),
                                        DatePicker::make('date_payment_voucher')
                                            ->label('Fecha del Comprobante de Pago')
                                            ->required()
                                            ->format('d/m/Y')
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
                                                ->label('Método de pago')
                                                ->options([
                                                    'ZELLE'             => 'ZELLE',
                                                    'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                    'EFECTIVO US$'      => 'EFECTIVO US$',
                                                    'MULTIPLE'          => 'MULTIPLE',
                                                    'PAGO MOVIL VES'    => 'PAGO MOVIL(VES)',
                                                    'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',

                                                ])
                                                ->live()
                                                ->required()
                                                ->validationMessages([
                                                    'required'  => 'Seleccione un tipo de pago',
                                                ]),
                                            TextInput::make('tasa_bcv')
                                                ->live()
                                                ->label('Tasa BCV')
                                                ->helperText('Punto(.) para separar decimales. Ejemplo: 123.45')
                                                ->prefix('VES')
                                                ->numeric()
                                                ->required()
                                                ->validationMessages([
                                                    'required'  => 'Campo requerido',
                                                    'numeric'   => 'El campo es numerico',
                                                ])
                                                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                                    if ($get('payment_method') == 'PAGO MOVIL VES' || $get('payment_method') == 'TRANSFERENCIA VES') {
                                                        $set('pay_amount_ves', $state * $get('total_amount'));
                                                    }
                                                    return $state;
                                                })
                                                ->hidden(function ($state, $set, Get $get) {
                                                    if ($get('payment_method') == 'MULTIPLE' || $get('payment_method') == 'PAGO MOVIL VES' || $get('payment_method') == 'TRANSFERENCIA VES') {
                                                        return false;
                                                    }
                                                    return true;
                                                })
                                        ])->columnSpan(3),


                                    /* PAGO EN DOLARES ZELLE */
                                    Fieldset::make('INFORMACION DE PAGO EN ZELLE (US$)')
                                        ->schema([
                                            TextInput::make('name_ti_usd')
                                                ->label('Nombre del Titular')
                                                ->helperText('Debe colocar Nombre y Apellido')
                                                ->prefixIcon('heroicon-s-pencil')
                                                ->required()
                                                ->validationMessages([
                                                    'required'  => 'Seleccione un tipo de pago',
                                                ]),
                                            TextInput::make('reference_payment_usd')
                                                ->label('Nro. de Referencia')
                                                ->helperText('Debe colocar el número de referencia completo')
                                                ->prefix('#')
                                                ->regex('/^[A-Za-z0-9\-]+$/')
                                                ->helperText('Solo se permiten letras, números y el guion (-)')
                                                ->required()
                                                ->validationMessages([
                                                    'regex'  => 'Solo se permite el guion (-)',
                                                    'required'  => 'Seleccione un tipo de pago',
                                                ]),

                                            Grid::make(1)->schema([
                                                FileUpload::make('document_usd')
                                                    ->label('Comprobante(US$)')
                                                    ->uploadingMessage('Cargando...')
                                                    ->required(),
                                            ])
                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'ZELLE') {
                                                return false;
                                            }
                                            return true;
                                        }),


                                    /** PAGO EN TRANSFERENCIA US$ */
                                    Fieldset::make('INFORMACIÓN DE PAGO EN TRANSFERENCIA (US$)')
                                        ->schema([
                                            Grid::make()->schema([
                                                TextInput::make('name_ti_usd')
                                                    ->label('Nombre del Titular')
                                                    ->helperText('Debe colocar Nombre y Apellido')
                                                    ->prefixIcon('heroicon-s-pencil')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required'  => 'Campo requerido',
                                                    ]),

                                                Select::make('bank_usd')
                                                    ->native(false)
                                                    ->label('Banco')
                                                    ->live()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required'  => 'Seleccione un banco',
                                                    ])
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
                                                        ->required(),
                                                ])
                                            ])->columnSpanFull(),
                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'TRANSFERENCIA US$') {
                                                return false;
                                            }
                                            return true;
                                        }),


                                    /** PAGO EN EFECTIVO US$ */
                                    Fieldset::make('INFORMACIÓN DE PAGO EN EFECTIVO (US$)')
                                        ->schema([
                                            Grid::make(2)->schema([
                                                Select::make('bank_usd')
                                                    ->native(false)
                                                    ->label('Banco')
                                                    ->live()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required'  => 'Seleccione un banco',
                                                    ])
                                                    ->options([
                                                        'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                                                        'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                                                    ])
                                                    ->searchable()
                                                    ->live()
                                                    ->prefixIcon('heroicon-s-globe-europe-africa'),


                                                Grid::make()->schema([
                                                    FileUpload::make('document_usd')
                                                        ->label('Comprobante(US$)')
                                                        ->uploadingMessage('Cargando...')
                                                        ->required(),
                                                ])->columnSpanFull(),
                                            ])->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'EFECTIVO US$') {
                                                    return false;
                                                }
                                                return true;
                                            })->columnSpanFull(),

                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'EFECTIVO US$') {
                                                return false;
                                            }
                                            return true;
                                        }),


                                    /* PAGO MOVIL Y TRANSFERENCIA */
                                    Fieldset::make('INFORMACIÓN DE PAGO EN MONEDA NACIONAL (VES)')
                                        ->schema([
                                            Grid::make(2)->schema([

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
                                                    ->label('Referencia de pago(VES)')
                                                    ->live()
                                                    ->inputMode('numeric') // activa teclado numérico en móvil
                                                    ->helperText('Últimos 6 dígitos del comprobante de pago')
                                                    ->mask('999999')
                                                    ->maxLength(6)
                                                    ->rules([
                                                        'regex:/^\d{1,6}$/' // Acepta de 1 a 6 dígitos
                                                    ])
                                                    ->prefix('Ref:'),
                                                Grid::make(1)->schema([
                                                    FileUpload::make('document_ves')
                                                        ->label('Comprobante de pago(VES)')
                                                        ->disk('public')
                                                        ->uploadingMessage('Cargando...')
                                                        ->required()
                                                ])

                                            ])->columnSpanFull(),
                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'TRANSFERENCIA VES' || $get('payment_method') == 'PAGO MOVIL VES' && $get('tasa_bcv') > 0) {
                                                return false;
                                            }
                                            return true;
                                        }),


                                    /** PAGO MULTIPLE */
                                    Fieldset::make('INFORMACIÓN DE PAGO MULTIPLE EN BOLIVARES (VES) Y DOLARES (US$)')
                                        ->schema([
                                            Grid::make(2)->schema([

                                                /* PAGO EN DOLARES(USD)) */
                                                Fieldset::make('PAGO EN DOLARES (US$)')
                                                    ->schema([
                                                        /**Metodo de pago en US$ */
                                                        Select::make('payment_method_usd')
                                                            ->live()
                                                            ->native(false)
                                                            ->label('Método de pago en dólares(US$)')
                                                            ->options([
                                                                'ZELLE'             => 'ZELLE',
                                                                'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                                'EFECTIVO US$'      => 'EFECTIVO US$',
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required'  => 'Seleccione un tipo de pago',
                                                            ]),

                                                        TextInput::make('pay_amount_usd')
                                                            ->inputMode('numeric') // activa teclado numérico en móvil
                                                            ->live(onBlur: true)
                                                            ->label('Monto US$:')
                                                            ->helperText('Punto(.) para separar decimales. Ingresa el monto en dólares(US$).')
                                                            ->prefix('US$')
                                                            ->numeric()
                                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                                $res = $get('total_amount') - $state;
                                                                Log::info($get('total_amount'));
                                                                Log::info($res);
                                                                Log::info($res / $get('tasa_bcv'));
                                                                $set('pay_amount_ves', $res * $get('tasa_bcv'));
                                                            }),

                                                        TextInput::make('name_ti_usd')
                                                            ->label('Nombre del Titular')
                                                            ->helperText('Debe colocar Nombre y Apellido')
                                                            ->prefixIcon('heroicon-s-pencil')
                                                            ->required()
                                                            ->validationMessages([
                                                                'required'  => 'Seleccione un tipo de pago',
                                                            ])
                                                            ->hidden(function (Get $get) {
                                                                if ($get('payment_method_usd') == 'TRANSFERENCIA US$' || $get('payment_method_usd') == 'ZELLE') {
                                                                    return false;
                                                                }
                                                                return true;
                                                            }),

                                                        /**Banco US$ */
                                                        Select::make('bank_usd')
                                                            ->native(false)
                                                            ->label('Banco Moneda Extranjera(US$)')
                                                            ->live()
                                                            ->options([
                                                                'CHASE BANK'                => 'CHASE BANK',
                                                                'BANK OF AMERICA'           => 'BANK OF AMERICA',
                                                                'BANESCO, S.A-US$'          => 'BANESCO, S.A - US$',
                                                                'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                                                                'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                                                            ])
                                                            ->searchable()
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),


                                                        TextInput::make('reference_payment_usd')
                                                            ->label('Nro. de Referencia')
                                                            ->helperText('Debe colocar el número de referencia completo')
                                                            ->prefix('#')
                                                            ->regex('/^[A-Za-z0-9\-]+$/')
                                                            ->helperText('Solo se permiten letras, números y el guion (-)')
                                                            ->required()
                                                            ->validationMessages([
                                                                'regex'  => 'Solo se permite el guion (-)',
                                                                'required'  => 'Seleccione un tipo de pago',
                                                            ])
                                                            ->hidden(function (Get $get) {
                                                                if ($get('payment_method_usd') == 'ZELLE') {
                                                                    return false;
                                                                }
                                                                return true;
                                                            }),

                                                        FileUpload::make('document_usd')
                                                            ->label('Comprobante de pago(US$)')
                                                            ->disk('public')
                                                            ->uploadingMessage('Cargando...'),

                                                    ])->columns(1),

                                                /* PAGO EN BOLIVARES (VES) */
                                                Fieldset::make('PAGO EN BOLIVARES (VES)')
                                                    ->schema([
                                                        /**Metodo de pago en VES */
                                                        Select::make('payment_method_ves')
                                                            ->native(false)
                                                            ->label('Método de pago en bolivares(VES)')
                                                            ->options([
                                                                'PAGO MOVIL VES'    => 'PAGO MOVIL(VES)',
                                                                'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required'  => 'Seleccione un tipo de pago',
                                                            ]),

                                                        TextInput::make('pay_amount_ves')
                                                            ->inputMode('numeric') // activa teclado numérico en móvil
                                                            ->label('Monto VES:')
                                                            ->helperText('Punto(.) para separar decimales. El Sistema calcula el restante en bolivares.')
                                                            ->prefix('VES')
                                                            ->numeric()
                                                            ->disabled()
                                                            ->dehydrated(),


                                                        /**Banco VES */
                                                        Select::make('bank_ves')
                                                            ->native(false)
                                                            ->label('Banco Moneda Nacional(VES)')
                                                            ->options([
                                                                'BANCAMIGA - VES'           => 'BANCAMIGA - VES',
                                                                'BANCO DE VENEZUELA - VES'  => 'BANCO DE VENEZUELA - VES',
                                                            ])
                                                            ->searchable()
                                                            ->required()
                                                            ->validationMessages([
                                                                'required'  => 'Seleccione un banco',
                                                            ])
                                                            ->prefixIcon('heroicon-s-globe-europe-africa'),



                                                        TextInput::make('reference_payment_ves')
                                                            ->label('Referencia de pago(VES)')
                                                            ->inputMode('numeric') // activa teclado numérico en móvil
                                                            ->helperText('Ultimos 6 dígitos del comprobante de pago')
                                                            ->mask('999999')
                                                            ->maxLength(6)
                                                            ->rules([
                                                                'regex:/^\d{1,6}$/' // Acepta de 1 a 6 dígitos
                                                            ])
                                                            ->required()
                                                            ->validationMessages([
                                                                'required'  => 'Campo requerido',
                                                            ])
                                                            ->prefix('Ref:'),
                                                        FileUpload::make('document_ves')
                                                            ->label('Comprobante de pago(VES)')
                                                            ->disk('public')
                                                            ->uploadingMessage('Cargando...')
                                                            ->required()
                                                            ->validationMessages([
                                                                'required'  => 'El comprobante es requerido',
                                                            ])
                                                    ])->columns(1),

                                            ])->columnSpanFull()
                                        ])->columnSpanFull()->hidden(function (Get $get) {
                                            if ($get('payment_method') == 'MULTIPLE' && $get('tasa_bcv') > 0) {
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
                                    ->dehydrated()
                            ]),
                        ])
                        ->action(function (Affiliation $record, array $data): void {
                            
                            try {

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

                                /**
                                 * Ejecutamos el Jobs para enviar la notificacion al 
                                 * correo de administracion
                                 * ----------------------------------------------------------------------------------
                                 */
                                $info = [
                                    'code' => $record->code,
                                    'email' => config('parameters.EMAIL_ADMINISTRACION'),
                                ];
                                // dd($info);
                                Mail::to($info['email'])->send(new UploadPayment($info));
                                
                            } catch (\Throwable $th) {
                                Log::error($th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-m-user-plus')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
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

                    /**DESCARGAR */
                    Action::make('download')
                        ->label('Descargar Certificado')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->requiresConfirmation()
                        ->modalHeading('DESCARGAR CERTIFICADO')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-s-arrow-down-on-square-stack')
                        ->modalDescription('Descargará un archivo PDF al hacer clic en confirmar!.')
                        ->action(function (Affiliation $record, array $data) {

                            try {

                                /**
                                 * Descargar el documento asociado a la cotizacion
                                 * ruta: storage/
                                 */
                                $path = public_path('storage/certificados-doc/CER-' . $record->code . '.pdf');
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

                    /**REENVIAR PROPUESTA */
                    Action::make('forward')
                        ->label('Reenviar Certificado')
                        ->icon('fluentui-document-arrow-right-20')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalIcon('fluentui-document-arrow-right-20')
                        ->modalHeading('Reenvío de Certificado')
                        ->modalDescription('El certificado será enviado por email y/o teléfono.')
                        ->modalWidth(Width::ExtraLarge)
                        ->form([
                            Section::make()
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


                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->color('azulOscuro')
                    ->hidden(function (Affiliation $record) {
                        return $record->status == 'ANULADA' || $record->status == 'EXCLUIDO';
                    })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('pay_multiple_affiliations')
                        ->label('Pagar afiliaciones')
                        ->icon('fontisto-share')
                        ->color('azulOscuro')
                        ->modalHeading('PAGO MASIVO DE AFILIACIONES')
                        ->modalDescription('El sistema calcula el total a pagar de acuerdo a la cantidad de afiliaciones seleccionadas.')
                        ->modalIcon('heroicon-m-shield-check')
                        ->modalWidth(Width::FourExtraLarge)
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->form(function (Collection $records) {

                            $data = $records->toArray();

                            //guardo la data en la sesion para usarla en el formulario
                            session()->put('data', $data);

                            return [

                                /** INFORMACION PRINCIPAL */
                                Fieldset::make('INFORMACION PRINCIPAL')
                                    ->schema([
                                        Grid::make(1)->schema([
                                            TextInput::make('total_amount')
                                                ->label('Total a pagar')
                                                ->prefix('US$')
                                                ->default(function () {
                                                    return array_sum(array_column(session()->get('data'), 'total_amount'));
                                                })
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
                                                    ->label('Método de pago')
                                                    ->options([
                                                        'ZELLE'             => 'ZELLE',
                                                        'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                        'EFECTIVO US$'      => 'EFECTIVO US$',
                                                        'MULTIPLE'          => 'MULTIPLE',
                                                        'PAGO MOVIL VES'    => 'PAGO MOVIL(VES)',
                                                        'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',

                                                    ])
                                                    ->live()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required'  => 'Seleccione un tipo de pago',
                                                    ]),
                                                TextInput::make('tasa_bcv')
                                                    ->live()
                                                    ->label('Tasa BCV')
                                                    ->helperText('Punto(.) para separar decimales. Ejemplo: 123.45')
                                                    ->prefix('VES')
                                                    ->numeric()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required'  => 'Campo requerido',
                                                        'numeric'   => 'El campo es numerico',
                                                    ])
                                                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                                        if ($get('payment_method') == 'PAGO MOVIL VES' || $get('payment_method') == 'TRANSFERENCIA VES') {
                                                            $set('pay_amount_ves', $state * $get('total_amount'));
                                                        }
                                                        return $state;
                                                    })
                                                    ->hidden(function ($state, $set, Get $get) {
                                                        if ($get('payment_method') == 'MULTIPLE' || $get('payment_method') == 'PAGO MOVIL VES' || $get('payment_method') == 'TRANSFERENCIA VES') {
                                                            return false;
                                                        }
                                                        return true;
                                                    })
                                            ])->columnSpan(3),


                                        /* PAGO EN DOLARES ZELLE */
                                        Fieldset::make('INFORMACION DE PAGO EN ZELLE (US$)')
                                            ->schema([
                                                TextInput::make('name_ti_usd')
                                                    ->label('Nombre del Titular')
                                                    ->helperText('Debe colocar Nombre y Apellido')
                                                    ->prefixIcon('heroicon-s-pencil')
                                                    ->required()
                                                    ->validationMessages([
                                                        'required'  => 'Seleccione un tipo de pago',
                                                    ]),
                                                TextInput::make('reference_payment_usd')
                                                    ->label('Nro. de Referencia')
                                                    ->helperText('Debe colocar el número de referencia completo')
                                                    ->prefix('#')
                                                    ->regex('/^[A-Za-z0-9\-]+$/')
                                                    ->helperText('Solo se permiten letras, números y el guion (-)')
                                                    ->required()
                                                    ->validationMessages([
                                                        'regex'  => 'Solo se permite el guion (-)',
                                                        'required'  => 'Seleccione un tipo de pago',
                                                    ]),

                                                Grid::make(1)->schema([
                                                    FileUpload::make('document_usd')
                                                        ->label('Comprobante(US$)')
                                                        ->uploadingMessage('Cargando...')
                                                        ->required(),
                                                ])
                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'ZELLE') {
                                                    return false;
                                                }
                                                return true;
                                            }),


                                        /** PAGO EN TRANSFERENCIA US$ */
                                        Fieldset::make('INFORMACIÓN DE PAGO EN TRANSFERENCIA (US$)')
                                            ->schema([
                                                Grid::make()->schema([
                                                    TextInput::make('name_ti_usd')
                                                        ->label('Nombre del Titular')
                                                        ->helperText('Debe colocar Nombre y Apellido')
                                                        ->prefixIcon('heroicon-s-pencil')
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Campo requerido',
                                                        ]),

                                                    Select::make('bank_usd')
                                                        ->native(false)
                                                        ->label('Banco')
                                                        ->live()
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Seleccione un banco',
                                                        ])
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
                                                            ->required(),
                                                    ])
                                                ])->columnSpanFull(),
                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'TRANSFERENCIA US$') {
                                                    return false;
                                                }
                                                return true;
                                            }),


                                        /** PAGO EN EFECTIVO US$ */
                                        Fieldset::make('INFORMACIÓN DE PAGO EN EFECTIVO (US$)')
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    Select::make('bank_usd')
                                                        ->native(false)
                                                        ->label('Banco')
                                                        ->live()
                                                        ->required()
                                                        ->validationMessages([
                                                            'required'  => 'Seleccione un banco',
                                                        ])
                                                        ->options([
                                                            'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                                                            'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                                                        ])
                                                        ->searchable()
                                                        ->live()
                                                        ->prefixIcon('heroicon-s-globe-europe-africa'),


                                                    Grid::make()->schema([
                                                        FileUpload::make('document_usd')
                                                            ->label('Comprobante(US$)')
                                                            ->uploadingMessage('Cargando...')
                                                            ->required(),
                                                    ])->columnSpanFull(),
                                                ])->hidden(function (Get $get) {
                                                    if ($get('payment_method') == 'EFECTIVO US$') {
                                                        return false;
                                                    }
                                                    return true;
                                                })->columnSpanFull(),

                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'EFECTIVO US$') {
                                                    return false;
                                                }
                                                return true;
                                            }),


                                        /* PAGO MOVIL Y TRANSFERENCIA */
                                        Fieldset::make('INFORMACIÓN DE PAGO EN MONEDA NACIONAL (VES)')
                                            ->schema([
                                                Grid::make(2)->schema([

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
                                                        ->label('Referencia de pago(VES)')
                                                        ->live()
                                                        ->inputMode('numeric') // activa teclado numérico en móvil
                                                        ->helperText('Últimos 6 dígitos del comprobante de pago')
                                                        ->mask('999999')
                                                        ->maxLength(6)
                                                        ->rules([
                                                            'regex:/^\d{1,6}$/' // Acepta de 1 a 6 dígitos
                                                        ])
                                                        ->prefix('Ref:'),
                                                    Grid::make(1)->schema([
                                                        FileUpload::make('document_ves')
                                                            ->label('Comprobante de pago(VES)')
                                                            ->disk('public')
                                                            ->uploadingMessage('Cargando...')
                                                            ->required()
                                                    ])

                                                ])->columnSpanFull(),
                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'TRANSFERENCIA VES' || $get('payment_method') == 'PAGO MOVIL VES' && $get('tasa_bcv') > 0) {
                                                    return false;
                                                }
                                                return true;
                                            }),


                                        /** PAGO MULTIPLE */
                                        Fieldset::make('INFORMACIÓN DE PAGO MULTIPLE EN BOLIVARES (VES) Y DOLARES (US$)')
                                            ->schema([
                                                Grid::make(2)->schema([

                                                    /* PAGO EN DOLARES(USD)) */
                                                    Fieldset::make('PAGO EN DOLARES (US$)')
                                                        ->schema([
                                                            /**Metodo de pago en US$ */
                                                            Select::make('payment_method_usd')
                                                                ->live()
                                                                ->native(false)
                                                                ->label('Método de pago en dólares(US$)')
                                                                ->options([
                                                                    'ZELLE'             => 'ZELLE',
                                                                    'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                                    'EFECTIVO US$'      => 'EFECTIVO US$',
                                                                ])
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required'  => 'Seleccione un tipo de pago',
                                                                ]),

                                                            TextInput::make('pay_amount_usd')
                                                                ->inputMode('numeric') // activa teclado numérico en móvil
                                                                ->live(onBlur: true)
                                                                ->label('Monto US$:')
                                                                ->helperText('Punto(.) para separar decimales. Ingresa el monto en dólares(US$).')
                                                                ->prefix('US$')
                                                                ->numeric()
                                                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                                    $res = $get('total_amount') - $state;
                                                                    Log::info($get('total_amount'));
                                                                    Log::info($res);
                                                                    Log::info($res / $get('tasa_bcv'));
                                                                    $set('pay_amount_ves', $res * $get('tasa_bcv'));
                                                                }),

                                                            TextInput::make('name_ti_usd')
                                                                ->label('Nombre del Titular')
                                                                ->helperText('Debe colocar Nombre y Apellido')
                                                                ->prefixIcon('heroicon-s-pencil')
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required'  => 'Seleccione un tipo de pago',
                                                                ])
                                                                ->hidden(function (Get $get) {
                                                                    if ($get('payment_method_usd') == 'TRANSFERENCIA US$' || $get('payment_method_usd') == 'ZELLE') {
                                                                        return false;
                                                                    }
                                                                    return true;
                                                                }),

                                                            /**Banco US$ */
                                                            Select::make('bank_usd')
                                                                ->native(false)
                                                                ->label('Banco Moneda Extranjera(US$)')
                                                                ->live()
                                                                ->options([
                                                                    'CHASE BANK'                => 'CHASE BANK',
                                                                    'BANK OF AMERICA'           => 'BANK OF AMERICA',
                                                                    'BANESCO, S.A-US$'          => 'BANESCO, S.A - US$',
                                                                    'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                                                                    'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                                                                ])
                                                                ->searchable()
                                                                ->prefixIcon('heroicon-s-globe-europe-africa'),


                                                            TextInput::make('reference_payment_usd')
                                                                ->label('Nro. de Referencia')
                                                                ->helperText('Debe colocar el número de referencia completo')
                                                                ->prefix('#')
                                                                ->regex('/^[A-Za-z0-9\-]+$/')
                                                                ->helperText('Solo se permiten letras, números y el guion (-)')
                                                                ->required()
                                                                ->validationMessages([
                                                                    'regex'  => 'Solo se permite el guion (-)',
                                                                    'required'  => 'Seleccione un tipo de pago',
                                                                ])
                                                                ->hidden(function (Get $get) {
                                                                    if ($get('payment_method_usd') == 'ZELLE') {
                                                                        return false;
                                                                    }
                                                                    return true;
                                                                }),

                                                            FileUpload::make('document_usd')
                                                                ->label('Comprobante de pago(US$)')
                                                                ->disk('public')
                                                                ->uploadingMessage('Cargando...'),

                                                        ])->columns(1),

                                                    /* PAGO EN BOLIVARES (VES) */
                                                    Fieldset::make('PAGO EN BOLIVARES (VES)')
                                                        ->schema([
                                                            /**Metodo de pago en VES */
                                                            Select::make('payment_method_ves')
                                                                ->native(false)
                                                                ->label('Método de pago en bolivares(VES)')
                                                                ->options([
                                                                    'PAGO MOVIL VES'    => 'PAGO MOVIL(VES)',
                                                                    'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                                                ])
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required'  => 'Seleccione un tipo de pago',
                                                                ]),

                                                            TextInput::make('pay_amount_ves')
                                                                ->inputMode('numeric') // activa teclado numérico en móvil
                                                                ->label('Monto VES:')
                                                                ->helperText('Punto(.) para separar decimales. El Sistema calcula el restante en bolivares.')
                                                                ->prefix('VES')
                                                                ->numeric()
                                                                ->disabled()
                                                                ->dehydrated(),


                                                            /**Banco VES */
                                                            Select::make('bank_ves')
                                                                ->native(false)
                                                                ->label('Banco Moneda Nacional(VES)')
                                                                ->options([
                                                                    'BANCAMIGA - VES'           => 'BANCAMIGA - VES',
                                                                    'BANCO DE VENEZUELA - VES'  => 'BANCO DE VENEZUELA - VES',
                                                                ])
                                                                ->searchable()
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required'  => 'Seleccione un banco',
                                                                ])
                                                                ->prefixIcon('heroicon-s-globe-europe-africa'),



                                                            TextInput::make('reference_payment_ves')
                                                                ->label('Referencia de pago(VES)')
                                                                ->inputMode('numeric') // activa teclado numérico en móvil
                                                                ->helperText('Ultimos 6 dígitos del comprobante de pago')
                                                                ->mask('999999')
                                                                ->maxLength(6)
                                                                ->rules([
                                                                    'regex:/^\d{1,6}$/' // Acepta de 1 a 6 dígitos
                                                                ])
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required'  => 'Campo requerido',
                                                                ])
                                                                ->prefix('Ref:'),
                                                            FileUpload::make('document_ves')
                                                                ->label('Comprobante de pago(VES)')
                                                                ->disk('public')
                                                                ->uploadingMessage('Cargando...')
                                                                ->required()
                                                                ->validationMessages([
                                                                    'required'  => 'El comprobante es requerido',
                                                                ])
                                                        ])->columns(1),

                                                ])->columnSpanFull()
                                            ])->columnSpanFull()->hidden(function (Get $get) {
                                                if ($get('payment_method') == 'MULTIPLE' && $get('tasa_bcv') > 0) {
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
                                        ->dehydrated()
                                ]),
                            ];
                        })
                        ->action(function (Collection $records, array $data) {
                            $upload = AffiliationController::uploadPaymentMultipleAffiliations($records, $data, 'AGENTE');

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
                                foreach ($records as $record) {
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
                            }
                        })
                    // ->canSee(function () {
                    //     return Auth::user()->hasPermissionTo('agents.pay_multiple_affiliations');
                    // }),
                ]),
            ])
            ->striped();
    }
}
<?php

namespace App\Filament\Business\Resources\Affiliations\Tables;

use Carbon\Carbon;
use App\Models\User;
use Filament\Tables\Table;
use App\Mail\UploadPayment;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
use Filament\Tables\Columns\ColumnGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Http\Controllers\AffiliationController;
use App\Filament\Resources\Affiliations\AffiliationResource;

class AffiliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->query(Affiliation::query()->where('ownerAccountManagers', Auth::user()->id))
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return Affiliation::query()->where('ownerAccountManagers', Auth::user()->id);
                }
                return Affiliation::query();
            })
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
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }
                            return 'danger';
                        })
                        ->searchable(),
                    TextColumn::make('fee_anual')
                        ->label('Tarifa Anual')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }
                            return 'danger';
                        })
                        ->searchable(),
                    //total_amount
                    TextColumn::make('total_amount')
                        ->label('Total a Pagar')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->color(function (mixed $state): string {
                            if ($state > 0) {
                                return 'warning';
                            }
                            return 'danger';
                        })
                        ->searchable(),
                    TextColumn::make('businessUnit.definition')
                        ->label('Unidad de Negocio')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('businessLine.definition')
                        ->label('Linea de Servicio')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('service_providers')
                        ->label('Proveedor(es) de Servicio')
                        ->badge()
                        ->color('success')
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
                ColumnGroup::make('Información ILS', [
                    TextColumn::make('vaucher_ils')
                        ->label('Voucher ILS')
                        ->badge()
                        ->alignCenter()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('date_payment_initial_ils')
                        ->label('ago ILS Desde')
                        ->badge()
                        ->alignCenter()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('date_payment_final_ils')
                        ->label('Pago ILS Hasta')
                        ->badge()
                        ->alignCenter()
                        ->color('success')
                        ->searchable(),
                ]),


                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),

                TextColumn::make('activated_at')
                    ->label('Fecha de Emisión')
                    ->color('warning')
                    ->icon('heroicon-s-calendar')
                    ->badge()
                    ->searchable(),

                TextInputColumn::make('effective_date')
                    ->label('Vigencia')
                    ->prefixIcon('heroicon-s-calendar')
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
                            'VENCIDA-POR-RENOVAR'   => 'danger',
                        };
                    })
                    ->searchable()
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'heroicon-c-information-circle',
                            'ACTIVA'                => 'heroicon-s-check-circle',
                            'PENDIENTE'             => 'heroicon-s-exclamation-circle',
                            'EXCLUIDO'              => 'heroicon-c-x-circle',
                            'VENCIDA-POR-RENOVAR'   => 'heroicon-c-x-circle',
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
                SelectFilter::make('plan_id')
                    ->label('Plan(es) afiliado(s)')
                    ->relationship('plan', 'description')
                    ->multiple(),
                SelectFilter::make('payment_frequency')
                    ->label('Frecuencia de Pago')
                    ->options([
                        'ANUAL'         => 'ANUAL',
                        'TRIMESTRAL'    => 'TRIMESTRAL',
                        'SEMESTRAL'     => 'SEMESTRAL',
                    ]),
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->recordActions([
                ActionGroup::make([

                    EditAction::make()
                        ->label('Editar')
                        ->color('warning')
                        ->icon('heroicon-o-pencil-square'),

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
                                Log::error($th);
                                Notification::make()
                                    ->title('ERROR')
                                    ->body('Ocurrio un error al registrar el comprobante de pago')
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
                                    'status'            => 'EXCLUIDO',
                                    'fee_anual'         => 0.0,
                                    'activated_at'      => null,
                                    'total_amount'      => 0.0,
                                    'family_members'    => 0
                                ]);
                                $record->affiliates()->update([
                                    'status'  => 'EXCLUIDO',
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
                        
                ])->hidden(fn($record) => $record->status == 'EXCLUIDO'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('pay_multiple_affiliations')
                        ->label('Pagar afiliaciones')
                        ->icon('fontisto-share')
                        ->color('success')
                        ->modalHeading('PAGO MULTIPLE DE AFILIACIONES')
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
                            }
                        })
                ]),
            ]);
    }
}
<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\Tables;

use Carbon\Carbon;
use App\Models\User;
use Filament\Tables\Table;
use App\Models\Affiliation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use App\Models\AffiliateCorporate;
use Filament\Tables\Filters\Filter;
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
use Filament\Tables\Columns\ColumnGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Http\Controllers\AffiliationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AffiliationCorporateController;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;
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
                    ->label('Codigo')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('name_corporate')
                    ->label('Cliente Corporativo')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),

                //...  
                ColumnGroup::make('Plan Afiliado', [
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia de pago')
                        ->alignCenter()
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('poblation')
                        ->label('Población')
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
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('total_amount')
                        ->label('Total a Pagar')
                        ->alignCenter()
                        ->money()
                        ->badge()
                        ->color('success')
                        ->searchable(),
                ]),

                TextColumn::make('rif')
                    ->label('Rif')
                    ->prefix('J-')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email contratante')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono contratante')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Direccion')
                    ->searchable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('country.name')
                    ->label('Pais')
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
                                        ->prefix('US$')
                                        ->default(function ($state, $set, Get $get, AffiliationCorporate $record) {
                                            $amount = $record->total_amount;
                                            return $amount;
                                        })
                                        ->numeric()
                                        ->live(),
                                        DatePicker::make('date_payment_voucher')
                                            ->label('Fecha del Comprobante de Pago')
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
                                            ])
                                            ->afterStateUpdatedJs(<<<'JS'
                                                    $set('name_ti_usd', $state.toUpperCase());
                                                JS),
                                        TextInput::make('reference_payment_zelle')
                                            ->label('Nro. de Referencia')
                                            ->inputMode('numeric') // activa teclado numérico en móvil
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
                                                ->afterStateUpdatedJs(<<<'JS'
                                                        $set('name_ti_usd', $state.toUpperCase());
                                                    JS)
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
                                                            $set('pay_amount_ves', $res * $get('tasa_bcv'));
                                                        }),

                                                    TextInput::make('name_ti_usd')
                                                        ->label('Nombre del Titular')
                                                        ->helperText('Debe colocar Nombre y Apellido')
                                                        ->prefixIcon('heroicon-s-pencil')
                                                        ->required()
                                                        ->afterStateUpdatedJs(<<<'JS'
                                                                $set('name_ti_usd', $state.toUpperCase());
                                                            JS)
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


                                                    TextInput::make('reference_payment_zelle')
                                                        ->inputMode('numeric') // activa teclado numérico en móvil
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
                                ->afterStateUpdatedJs(<<<'JS'
                                        $set('observations_payment', $state.toUpperCase());
                                    JS)
                                ->rows(2)
                                ->autosize()
                                ->dehydrated()
                        ]),
                    ])
                    ->action(function (AffiliationCorporate $record, array $data): void {
                        // dd($data, $record);
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
                                            ->url(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'business') . '?activeRelationManager=1'),
                                    ])
                                    ->sendToDatabase($recipient_for_user);
                            }

                            /**
                             * Notificación para el usuario que creo la cotización
                             * ----------------------------------------------------------------------------------
                             */
                            NotificationController::uploadVoucherOfPayment($record->code, Auth::user()->name);
                        }
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
<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

final class PaymentVoucherFormSchema
{
    /**
     * @param  Closure(mixed): float  $baseTotalDefault
     * @param  Closure(mixed): string  $totalHelperText
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(Closure $baseTotalDefault, Closure $totalHelperText): array
    {
        return [

            /** INFORMACION PRINCIPAL */
            Fieldset::make('INFORMACION PRINCIPAL')
                ->schema([
                    Hidden::make('base_total_amount')
                        ->default(fn (mixed $record): float => (float) ($baseTotalDefault)($record))
                        ->dehydrated(),
                    ...self::bcvRateManualStateHiddenFields(),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('total_amount')
                            ->label('Total a pagar')
                            ->helperText(fn (mixed $record): string => ($totalHelperText)($record))
                            ->prefix('US$')
                            ->default(fn (mixed $record): float => (float) ($baseTotalDefault)($record))
                            ->numeric()
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                self::syncPaymentBcvRateFromTotal($get, $set, $state);
                            }),
                        TextInput::make('payment_adjustment_percentage')
                            ->label('Ajuste del total (%)')
                            ->helperText('Valores positivos aumentan y negativos disminuyen el total a pagar.')
                            ->numeric()
                            ->default(0)
                            ->suffix('%')
                            ->live(onBlur: false)
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::applyPaymentTotalPercentageAdjustment($get, $set);
                            }),
                    ])->columnSpanFull(),
                    Placeholder::make('payment_total_preview')
                        ->label('Cálculo del total')
                        ->content(fn (Get $get): HtmlString => self::paymentTotalPreviewHtml($get))
                        ->columnSpanFull(),
                    DatePicker::make('date_payment_voucher')
                        ->label('Fecha del Comprobante de Pago')
                        ->required()
                        ->format('d/m/Y')
                        ->columnSpanFull(),
                ])->columnSpanFull(),

            /**FORMA DE PAGO */
            Fieldset::make('FORMA DE PAGO')
                ->schema([

                    /**SELECCION DEL METODO DE PAGO */
                    Grid::make(1)
                        ->schema([
                            Select::make('payment_method')
                                ->native(false)
                                ->label('Método de pago')
                                ->options([
                                    'ZELLE' => 'ZELLE',
                                    'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                    'EFECTIVO US$' => 'EFECTIVO US$',
                                    'MULTIPLE' => 'MULTIPLE',
                                    'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
                                    'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                    'LINK DE PAGO' => 'LINK DE PAGO',
                                ])
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                    self::resetBcvRateManualState($set);

                                    if (in_array($state, ['PAGO MOVIL VES', 'TRANSFERENCIA VES', 'MULTIPLE'], true)) {
                                        self::syncPaymentBcvRateFromTotal($get, $set, $get('total_amount'));
                                    }
                                })
                                ->validationMessages([
                                    'required' => 'Seleccione un tipo de pago',
                                ]),
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
                                    'required' => 'Seleccione un tipo de pago',
                                ]),
                            TextInput::make('reference_payment_usd')
                                ->label('Nro. de Referencia')
                                ->helperText('Debe colocar el número de referencia completo')
                                ->prefix('#')
                                ->regex('/^[A-Za-z0-9\-]+$/')
                                ->helperText('Solo se permiten letras, números y el guion (-)')
                                ->required()
                                ->validationMessages([
                                    'regex' => 'Solo se permite el guion (-)',
                                    'required' => 'Seleccione un tipo de pago',
                                ]),

                            Grid::make(1)->schema([
                                FileUpload::make('document_usd')
                                    ->label('Comprobante(US$)')
                                    ->uploadingMessage('Cargando...')
                                    ->required(),
                            ]),
                        ])->columnSpanFull()->hidden(function (Get $get) {
                            if ($get('payment_method') == 'ZELLE') {
                                return false;
                            }

                            return true;
                        }),

                    /* PAGO EN DOLARES LINK DE PAGO */
                    Fieldset::make('INFORMACION DE PAGO EN LINK DE PAGO (US$)')
                        ->schema([
                            TextInput::make('name_ti_usd')
                                ->label('Nombre del Titular')
                                ->helperText('Debe colocar Nombre y Apellido')
                                ->prefixIcon('heroicon-s-pencil')
                                ->required()
                                ->validationMessages([
                                    'required' => 'Seleccione un tipo de pago',
                                ]),
                            TextInput::make('reference_payment_usd')
                                ->label('Nro. de Referencia')
                                ->helperText('Debe colocar el número de referencia completo')
                                ->prefix('#')
                                ->regex('/^[A-Za-z0-9\-]+$/')
                                ->helperText('Solo se permiten letras, números y el guion (-)')
                                ->required()
                                ->validationMessages([
                                    'regex' => 'Solo se permite el guion (-)',
                                    'required' => 'Seleccione un tipo de pago',
                                ]),

                            Grid::make(1)->schema([
                                FileUpload::make('document_usd')
                                    ->label('Comprobante(US$)')
                                    ->uploadingMessage('Cargando...')
                                    ->required(),
                            ]),
                        ])->columnSpanFull()->hidden(function (Get $get) {
                            if ($get('payment_method') == 'LINK DE PAGO') {
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
                                        'required' => 'Campo requerido',
                                    ]),

                                Select::make('bank_usd')
                                    ->native(false)
                                    ->label('Banco')
                                    ->live()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Seleccione un banco',
                                    ])
                                    ->options([
                                        'CHASE BANK' => 'CHASE BANK',
                                        'BANK OF AMERICA' => 'BANK OF AMERICA',
                                        'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                                        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
                                    ])
                                    ->searchable()
                                    ->live()
                                    ->prefixIcon('heroicon-s-globe-europe-africa'),

                                Grid::make(1)->schema([
                                    FileUpload::make('document_usd')
                                        ->label('Comprobante(US$)')
                                        ->uploadingMessage('Cargando...')
                                        ->required(),
                                ]),
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
                                        'required' => 'Seleccione un banco',
                                    ])
                                    ->options([
                                        'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                        'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
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
                                    ->inputMode('numeric')
                                    ->live(onBlur: true)
                                    ->label('Monto recibido en VES')
                                    ->helperText('Ingrese el monto en bolívares recibido. La tasa BCV se calcula automáticamente (VES ÷ total US$).')
                                    ->prefix('VES')
                                    ->numeric()
                                    ->required(fn (Get $get): bool => in_array($get('payment_method'), ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true))
                                    ->validationMessages([
                                        'required' => 'Campo requerido',
                                        'numeric' => 'El campo es numérico',
                                    ])
                                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                        self::syncPaymentBcvRateFromVesAmount($get, $set, $state);
                                    }),
                                self::bcvRateTextInput(),
                                Select::make('bank_ves')
                                    ->native(false)
                                    ->label('Banco')
                                    ->live()
                                    ->options([
                                        'BANCAMIGA(VES)' => 'BANCAMIGA',
                                        'BANCO DE VENEZUELA(VES)' => 'BANCO DE VENEZUELA',
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
                                        'regex:/^\d{1,6}$/', // Acepta de 1 a 6 dígitos
                                    ])
                                    ->prefix('Ref:'),
                                Grid::make(1)->schema([
                                    FileUpload::make('document_ves')
                                        ->label('Comprobante de pago(VES)')
                                        ->disk('public')
                                        ->uploadingMessage('Cargando...')
                                        ->required(),
                                ]),

                            ])->columnSpanFull(),
                        ])->columnSpanFull()->hidden(function (Get $get): bool {
                            return ! in_array($get('payment_method'), ['TRANSFERENCIA VES', 'PAGO MOVIL VES'], true);
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
                                                'ZELLE' => 'ZELLE',
                                                'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                                                'EFECTIVO US$' => 'EFECTIVO US$',
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Seleccione un tipo de pago',
                                            ]),

                                        TextInput::make('pay_amount_usd')
                                            ->inputMode('numeric') // activa teclado numérico en móvil
                                            ->live(onBlur: true)
                                            ->label('Monto US$:')
                                            ->helperText('Punto(.) para separar decimales. Ingresa el monto en dólares(US$). La tasa BCV se recalcula con el monto en bolívares.')
                                            ->prefix('US$')
                                            ->numeric()
                                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                self::syncPaymentBcvRateFromUsdPart($get, $set, $state);
                                            }),

                                        TextInput::make('name_ti_usd')
                                            ->label('Nombre del Titular')
                                            ->helperText('Debe colocar Nombre y Apellido')
                                            ->prefixIcon('heroicon-s-pencil')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Seleccione un tipo de pago',
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
                                                'CHASE BANK' => 'CHASE BANK',
                                                'BANK OF AMERICA' => 'BANK OF AMERICA',
                                                'BANESCO, S.A-US$' => 'BANESCO, S.A - US$',
                                                'BANCAMIGA - US$' => 'BANCAMIGA - US$',
                                                'BANCO DE VENEZUELA - US$' => 'BANCO DE VENEZUELA - US$',
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
                                                'regex' => 'Solo se permite el guion (-)',
                                                'required' => 'Seleccione un tipo de pago',
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
                                                'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
                                                'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Seleccione un tipo de pago',
                                            ]),

                                        TextInput::make('pay_amount_ves')
                                            ->inputMode('numeric')
                                            ->live(onBlur: true)
                                            ->label('Monto VES:')
                                            ->helperText('Ingrese el monto en bolívares del saldo restante. La tasa BCV se calcula automáticamente (VES ÷ saldo US$).')
                                            ->prefix('VES')
                                            ->numeric()
                                            ->required(fn (Get $get): bool => $get('payment_method') === 'MULTIPLE')
                                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                                self::syncPaymentBcvRateFromVesAmount($get, $set, $state);
                                            }),

                                        self::bcvRateTextInput(),

                                        /**Banco VES */
                                        Select::make('bank_ves')
                                            ->native(false)
                                            ->label('Banco Moneda Nacional(VES)')
                                            ->options([
                                                'BANCAMIGA - VES' => 'BANCAMIGA - VES',
                                                'BANCO DE VENEZUELA - VES' => 'BANCO DE VENEZUELA - VES',
                                            ])
                                            ->searchable()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Seleccione un banco',
                                            ])
                                            ->prefixIcon('heroicon-s-globe-europe-africa'),

                                        TextInput::make('reference_payment_ves')
                                            ->label('Referencia de pago(VES)')
                                            ->inputMode('numeric') // activa teclado numérico en móvil
                                            ->helperText('Ultimos 6 dígitos del comprobante de pago')
                                            ->mask('999999')
                                            ->maxLength(6)
                                            ->rules([
                                                'regex:/^\d{1,6}$/', // Acepta de 1 a 6 dígitos
                                            ])
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Campo requerido',
                                            ])
                                            ->prefix('Ref:'),
                                        FileUpload::make('document_ves')
                                            ->label('Comprobante de pago(VES)')
                                            ->disk('public')
                                            ->uploadingMessage('Cargando...')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'El comprobante es requerido',
                                            ]),
                                    ])->columns(1),

                            ])->columnSpanFull(),
                        ])->columnSpanFull()->hidden(function (Get $get): bool {
                            return $get('payment_method') !== 'MULTIPLE';
                        }),

                ]),

            /**OBSERVACIONES */
            Grid::make(1)->schema([
                Textarea::make('observations_payment')
                    ->label('Observaciones')
                    ->rows(2)
                    ->autosize()
                    ->dehydrated(),
            ]),
        ];
    }

    private static function paymentTotalPreviewHtml(Get $get): HtmlString
    {
        $base = AffiliationPaymentTotalAdjustment::parseAmount($get('base_total_amount'))
            ?? AffiliationPaymentTotalAdjustment::parseAmount($get('total_amount'))
            ?? 0.0;
        $percentage = is_numeric($get('payment_adjustment_percentage'))
            ? (float) $get('payment_adjustment_percentage')
            : 0.0;
        $bcvRate = AffiliationPaymentTotalAdjustment::parseAmount($get('tasa_bcv'))
            ?? BcvOfficialRate::resolve();

        return AffiliationPaymentTotalAdjustment::previewHtml($base, $percentage, $bcvRate);
    }

    private static function applyPaymentTotalPercentageAdjustment(Get $get, Set $set): void
    {
        $base = AffiliationPaymentTotalAdjustment::parseAmount($get('base_total_amount'));

        if ($base === null) {
            return;
        }

        $percentage = is_numeric($get('payment_adjustment_percentage'))
            ? (float) $get('payment_adjustment_percentage')
            : 0.0;

        $adjustedTotal = AffiliationPaymentTotalAdjustment::adjust($base, $percentage);
        $set('total_amount', $adjustedTotal);
        self::syncPaymentBcvRateFromTotal($get, $set, $adjustedTotal);
    }

    private static function syncPaymentBcvRateFromTotal(Get $get, Set $set, mixed $totalAmount): void
    {
        if (! self::shouldAutoCalculateBcvRate($get)) {
            return;
        }

        $paymentMethod = $get('payment_method');

        if (in_array($paymentMethod, ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true)) {
            self::applyCalculatedBcvRate(
                $set,
                AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($get('pay_amount_ves'), $totalAmount),
            );

            return;
        }

        if ($paymentMethod === 'MULTIPLE') {
            self::syncMultiplePaymentBcvRate($get, $set, $totalAmount);
        }
    }

    private static function syncPaymentBcvRateFromVesAmount(Get $get, Set $set, mixed $vesAmount): void
    {
        if (! self::shouldAutoCalculateBcvRate($get)) {
            return;
        }

        if (in_array($get('payment_method'), ['PAGO MOVIL VES', 'TRANSFERENCIA VES'], true)) {
            self::applyCalculatedBcvRate(
                $set,
                AffiliationPaymentBcvRateCalculator::rateFromVesAndUsdTotal($vesAmount, $get('total_amount')),
            );

            return;
        }

        if ($get('payment_method') === 'MULTIPLE') {
            self::syncMultiplePaymentBcvRate($get, $set, $get('total_amount'), $vesAmount);
        }
    }

    private static function syncPaymentBcvRateFromUsdPart(Get $get, Set $set, mixed $usdPart): void
    {
        if (! self::shouldAutoCalculateBcvRate($get)) {
            return;
        }

        if ($get('payment_method') !== 'MULTIPLE') {
            return;
        }

        self::syncMultiplePaymentBcvRate($get, $set, $get('total_amount'), $get('pay_amount_ves'), $usdPart);
    }

    private static function syncMultiplePaymentBcvRate(
        Get $get,
        Set $set,
        mixed $totalAmount,
        mixed $vesAmount = null,
        mixed $usdPart = null,
    ): void {
        $total = AffiliationPaymentBcvRateCalculator::positiveAmount($totalAmount ?? $get('total_amount'));
        $usd = AffiliationPaymentBcvRateCalculator::nonNegativeFloat($usdPart ?? $get('pay_amount_usd'));

        if ($total === null || $usd === null) {
            return;
        }

        $remainingUsd = $total - $usd;

        if ($remainingUsd <= 0) {
            return;
        }

        self::applyCalculatedBcvRate(
            $set,
            AffiliationPaymentBcvRateCalculator::rateFromVesAndRemainingUsd(
                $vesAmount ?? $get('pay_amount_ves'),
                $remainingUsd,
            ),
        );
    }

    private static function applyCalculatedBcvRate(Set $set, ?string $rate): void
    {
        if ($rate === null) {
            return;
        }

        self::setCalculatedBcvRate($set, $rate);
    }

    /**
     * @return array<int, Hidden>
     */
    private static function bcvRateManualStateHiddenFields(): array
    {
        return [
            Hidden::make('tasa_bcv_manual')
                ->default(false)
                ->dehydrated(false),
            Hidden::make('tasa_bcv_calculated')
                ->dehydrated(false),
        ];
    }

    private static function bcvRateTextInput(string $helperText = ''): TextInput
    {
        $helper = 'Bs por US$: se calcula automáticamente al dividir el monto en bolívares entre el total en US$.';
        if ($helperText !== '') {
            $helper .= ' '.$helperText;
        }

        return TextInput::make('tasa_bcv')
            ->label('Tasa BCV (calculada)')
            ->helperText($helper)
            ->prefix('VES')
            ->numeric()
            ->disabled()
            ->dehydrated();
    }

    private static function setCalculatedBcvRate(Set $set, ?string $rate): void
    {
        if ($rate === null) {
            return;
        }

        $set('tasa_bcv_calculated', $rate);
        $set('tasa_bcv', $rate);
        $set('tasa_bcv_manual', false);
    }

    private static function resetBcvRateManualState(Set $set): void
    {
        $set('tasa_bcv_manual', false);
        $set('tasa_bcv_calculated', null);
    }

    private static function shouldAutoCalculateBcvRate(Get $get): bool
    {
        return ! filter_var($get('tasa_bcv_manual'), FILTER_VALIDATE_BOOLEAN);
    }

    private static function syncBcvRateManualFlag(Get $get, Set $set, mixed $state): void
    {
        $calculated = $get('tasa_bcv_calculated');

        if ($calculated === null || $calculated === '') {
            return;
        }

        $set('tasa_bcv_manual', ! self::bcvRatesMatch($state, $calculated));
    }

    private static function bcvRatesMatch(mixed $a, mixed $b): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return false;
        }

        return round((float) $a, 6) === round((float) $b, 6);
    }
}

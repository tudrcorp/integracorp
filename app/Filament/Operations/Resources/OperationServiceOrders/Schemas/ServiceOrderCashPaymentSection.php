<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationServiceOrders\Schemas;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Models\OperationCashPayment;
use App\Models\OperationServiceOrder;
use App\Support\BankCatalog;
use App\Support\BcvOfficialRate;
use App\Support\Operations\AccountsPayablePaymentReceiptRegistrar;
use App\Support\Operations\CashPayments\CashPaymentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/**
 * Sección «Pago de contado» de la modal «Cargar factura»: si el proveedor se pagó en el acto,
 * el analista registra fecha, referencia, banco, monto (el total de la factura) y comprobante,
 * y la cuenta por pagar queda PAGADA. El pago se ve y se reversa en «Pagos de contado».
 */
final class ServiceOrderCashPaymentSection
{
    public static function make(OperationServiceOrder $record, string $sectionClass): Section
    {
        $active = self::activePayment($record);

        return Section::make('Pago de contado')
            ->description('Si el proveedor ya se pagó al momento de facturar, regístralo aquí: la cuenta por pagar queda pagada y el pago queda en «Pagos de contado».')
            ->icon('heroicon-m-credit-card')
            ->schema($active !== null ? [
                Placeholder::make('cash_payment_registered')
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<p class="rounded-xl bg-success-50 px-4 py-3 text-sm text-success-800 dark:bg-success-500/10 dark:text-success-300">'
                        .'Esta factura ya tiene un pago de contado registrado: '
                        .e(CashPaymentService::money((float) $active->payment_amount, (string) $active->payment_currency))
                        .' · ref. '.e((string) $active->payment_reference).' · '.e($active->payment_date?->format('d/m/Y') ?? '')
                        .'. Para cambiarlo, revérselo en «Pagos de contado».</p>'
                    )),
            ] : [
                Toggle::make('cash_paid')
                    ->label('Se pagó de contado')
                    ->helperText(self::payableIsPending($record)
                        ? 'Actívalo solo si el pago ya se hizo. Debe cubrir el total de la factura.'
                        : 'La cuenta por pagar de esta factura ya tiene un pago registrado: no se puede pagar de contado.')
                    ->disabled(! self::payableIsPending($record))
                    ->live(),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->visible(fn (Get $get): bool => (bool) $get('cash_paid'))
                    ->schema([
                        DatePicker::make('cash_payment_date')
                            ->label('Fecha del pago')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now()->startOfDay())
                            ->maxDate(now())
                            ->minDate(fn (Get $get) => $get('invoice_date') ?: null)
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid'))
                            ->validationMessages([
                                'required' => 'Indica la fecha del pago.',
                                'before_or_equal' => 'La fecha del pago no puede ser posterior a hoy.',
                                'after_or_equal' => 'La fecha del pago no puede ser anterior a la emisión de la factura.',
                            ]),
                        TextInput::make('cash_payment_reference')
                            ->label('Referencia')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->placeholder('Ej. 001234567890')
                            ->maxLength(120)
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid'))
                            ->validationMessages(['required' => 'Indica la referencia del pago.']),
                        ToggleButtons::make('cash_payment_currency')
                            ->label('Moneda del pago')
                            ->options(['USD' => 'US$', 'VES' => 'Bolívares'])
                            ->icons(['USD' => 'heroicon-m-currency-dollar', 'VES' => 'heroicon-m-banknotes'])
                            ->inline()
                            ->default('USD')
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid'))
                            ->live(),
                        BankCatalog::configureInternationalSelect(Select::make('cash_payment_bank_international'))
                            ->label('Banco (internacional)')
                            ->helperText('Banco desde donde se hizo el pago en US$.')
                            ->visible(fn (Get $get): bool => $get('cash_payment_currency') !== 'VES')
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid') && $get('cash_payment_currency') !== 'VES')
                            ->validationMessages(['required' => 'Indica el banco desde donde se hizo el pago.']),
                        BankCatalog::configureNationalSelect(Select::make('cash_payment_bank_national'))
                            ->label('Banco (nacional)')
                            ->helperText('Banco venezolano desde donde se hizo el pago en bolívares.')
                            ->visible(fn (Get $get): bool => $get('cash_payment_currency') === 'VES')
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid') && $get('cash_payment_currency') === 'VES')
                            ->validationMessages(['required' => 'Indica el banco desde donde se hizo el pago.']),
                        TextInput::make('cash_payment_amount')
                            ->label('Monto del pago')
                            ->prefix(fn (Get $get): string => $get('cash_payment_currency') === 'VES' ? 'Bs.' : 'US$')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid'))
                            ->helperText('Debe cubrir el total de la factura (pago total).')
                            ->validationMessages(['required' => 'Indica el monto del pago.']),
                        TextInput::make('cash_bcv_rate')
                            ->label('Tasa BCV del pago (Bs. por US$)')
                            ->prefix('Bs.')
                            ->numeric()
                            ->minValue(0.0001)
                            ->default(fn (): ?float => BcvOfficialRate::suggested())
                            ->helperText('Se usa porque el pago es en otra moneda que la factura. Se sugiere la tasa oficial de hoy.')
                            ->visible(fn (Get $get): bool => (bool) $get('cash_paid') && $get('cash_payment_currency') !== self::invoiceCurrency($get))
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid') && $get('cash_payment_currency') !== self::invoiceCurrency($get))
                            ->validationMessages(['required' => 'Indica la tasa BCV para convertir el pago a la moneda de la factura.']),
                        FileUpload::make('cash_payment_receipt')
                            ->label('Comprobante del pago')
                            ->disk(AccountsPayablePaymentReceiptRegistrar::DISK)
                            ->directory(AccountsPayablePaymentReceiptRegistrar::DIRECTORY)
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->required(fn (Get $get): bool => (bool) $get('cash_paid'))
                            ->downloadable()
                            ->openable()
                            ->helperText('Formatos: PDF, JPG, PNG o WebP. Máximo 4 MB.')
                            ->validationMessages(['required' => 'Adjunta el comprobante del pago.'])
                            ->columnSpanFull(),
                    ]),
            ])
            ->columns(1)
            ->columnSpanFull()
            ->extraAttributes(['class' => $sectionClass]);
    }

    /**
     * Datos del formulario con el banco que corresponde a la moneda del pago.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function paymentData(array $data): array
    {
        return [
            ...$data,
            'cash_payment_bank' => ($data['cash_payment_currency'] ?? 'USD') === 'VES'
                ? ($data['cash_payment_bank_national'] ?? null)
                : ($data['cash_payment_bank_international'] ?? null),
        ];
    }

    public static function activePayment(OperationServiceOrder $record): ?OperationCashPayment
    {
        return OperationCashPayment::query()
            ->where('operation_service_order_id', $record->getKey())
            ->where('status', OperationCashPayment::STATUS_REGISTERED)
            ->latest('id')
            ->first();
    }

    private static function payableIsPending(OperationServiceOrder $record): bool
    {
        $payable = OperationAccountsPayable::query()->where('operation_service_order_id', $record->getKey())->first();

        return ! $payable instanceof OperationAccountsPayable
            || StatusCuentaPorPagar::fromStored($payable->payment_status) === StatusCuentaPorPagar::PendientePorPagar;
    }

    /**
     * Moneda de la factura según lo cargado en el formulario: US$ si hay monto en US$; si no, Bs.
     */
    private static function invoiceCurrency(Get $get): string
    {
        $usd = $get('invoice_amount_usd');

        return is_numeric($usd) && (float) $usd > 0 ? 'USD' : 'VES';
    }
}

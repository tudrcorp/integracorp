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
use App\Support\Operations\ServiceOrderBulkInvoice;
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
use Filament\Schemas\Components\Utilities\Set;
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
                self::paymentFields(),
            ])
            ->columns(1)
            ->columnSpanFull()
            ->extraAttributes(['class' => $sectionClass]);
    }

    /**
     * Valores iniciales de la sección. Las modales se rellenan con `fillForm`, que no
     * aplica los `default()` de los campos: sin esto la fecha, la moneda y la tasa
     * llegaban vacías.
     *
     * @return array<string, mixed>
     */
    public static function initialState(?float $invoiceAmountUsd): array
    {
        return [
            'cash_paid' => false,
            'cash_payment_date' => now()->toDateString(),
            'cash_payment_currency' => 'USD',
            'cash_payment_amount' => $invoiceAmountUsd !== null && $invoiceAmountUsd > 0 ? round($invoiceAmountUsd, 2) : null,
            'cash_bcv_rate' => BcvOfficialRate::suggested(),
        ];
    }

    /**
     * Deja el monto del pago igual al total de la factura, en la moneda del pago
     * (convertido a la tasa BCV si la moneda es otra). Se llama cuando cambian el
     * total de la factura, la moneda o la tasa; el analista puede editarlo después.
     */
    public static function syncPaymentAmount(Get $get, Set $set): void
    {
        $amount = self::invoiceTotalInCurrency(
            $get('invoice_amount_usd'),
            $get('invoice_amount_ves'),
            (string) ($get('cash_payment_currency') ?: 'USD'),
            $get('cash_bcv_rate'),
        );

        if ($amount !== null) {
            $set('cash_payment_amount', $amount);
        }
    }

    /**
     * Total de la factura expresado en la moneda del pago; null si falta el monto o la tasa.
     */
    public static function invoiceTotalInCurrency(mixed $invoiceUsd, mixed $invoiceVes, string $paymentCurrency, mixed $rate): ?float
    {
        $usd = is_numeric($invoiceUsd) && (float) $invoiceUsd > 0 ? (float) $invoiceUsd : null;
        $ves = is_numeric($invoiceVes) && (float) $invoiceVes > 0 ? (float) $invoiceVes : null;
        [$invoiceAmount, $invoiceCurrency] = $usd !== null ? [$usd, 'USD'] : [$ves, 'VES'];

        if ($invoiceAmount === null) {
            return null;
        }

        if ($paymentCurrency === $invoiceCurrency) {
            return round($invoiceAmount, 2);
        }

        $rate = is_numeric($rate) && (float) $rate > 0 ? (float) $rate : null;

        if ($rate === null) {
            return null;
        }

        return round($invoiceCurrency === 'USD' ? $invoiceAmount * $rate : $invoiceAmount / $rate, 2);
    }

    /**
     * Campos del pago: fecha, referencia, moneda, banco, monto, tasa BCV y comprobante.
     * Los usan la carga individual y la masiva.
     */
    public static function paymentFields(): Grid
    {
        return Grid::make(['default' => 1, 'lg' => 2])
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
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncPaymentAmount($get, $set)),
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
                    ->helperText('Se rellena con el total de la factura en la moneda del pago. Debe cubrirlo completo.')
                    ->validationMessages(['required' => 'Indica el monto del pago.']),
                TextInput::make('cash_bcv_rate')
                    ->label('Tasa BCV del pago (Bs. por US$)')
                    ->prefix('Bs.')
                    ->numeric()
                    ->minValue(0.0001)
                    ->default(fn (): ?float => BcvOfficialRate::suggested())
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncPaymentAmount($get, $set))
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
            ]);
    }

    /**
     * Sección «Pago de contado» de la carga masiva: un solo pago por el total de la
     * factura, repartido entre las órdenes. Se bloquea si alguna ya tiene un pago.
     *
     * @param  list<string>  $blockers  órdenes seleccionadas que ya tienen un pago
     */
    public static function makeForBulk(int $orderCount, array $blockers, string $sectionClass): Section
    {
        return Section::make('Pago de contado')
            ->description('Si el proveedor ya cobró esta factura, regístralo aquí: el pago se reparte entre las '.$orderCount.' órdenes con la misma proporción que la factura, sus cuentas por pagar quedan pagadas y el pago queda en «Pagos de contado» como un solo pago masivo (se reversa completo).')
            ->icon('heroicon-m-credit-card')
            ->schema([
                Toggle::make('cash_paid')
                    ->label('Se pagó de contado')
                    ->helperText($blockers === []
                        ? 'Actívalo solo si el pago ya se hizo. Debe cubrir el total de la factura.'
                        : ServiceOrderBulkInvoice::cashPaymentBlockerMessage($blockers))
                    ->disabled($blockers !== [])
                    ->live(),
                self::paymentFields(),
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
        return CashPaymentService::paymentData($data);
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

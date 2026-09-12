<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Actions;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Support\BankCatalog;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\AccountsPayablePaymentReceiptRegistrar;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

final class AccountsPayablePaymentReceiptActions
{
    public static function makeRecordAction(): Action
    {
        return self::configureModal(
            Action::make('uploadPaymentReceipt')
                ->label(fn (OperationAccountsPayable $record): string => $record->hasPaymentReceipt()
                    ? 'Actualizar comprobante'
                    : 'Cargar comprobante')
                ->modalHeading(fn (OperationAccountsPayable $record): string => $record->hasPaymentReceipt()
                    ? 'Actualizar comprobante de pago'
                    : 'Cargar comprobante de pago')
                ->modalDescription(fn (OperationAccountsPayable $record): string => 'Factura '.$record->invoice_number
                    .' · '.$record->supplier_name
                    .' · '.self::money($record->invoice_amount, $record->invoice_currency))
                ->fillForm(fn (OperationAccountsPayable $record): array => self::fillFromRecord($record))
                ->form(fn (OperationAccountsPayable $record): array => self::schema($record, 1, $record->hasPaymentReceipt()))
                ->action(function (OperationAccountsPayable $record, array $data): void {
                    self::run(collect([$record]), $data);
                }),
        );
    }

    public static function makeBulkAction(): BulkAction
    {
        return self::configureModal(
            BulkAction::make('uploadPaymentReceipts')
                ->label('Cargar comprobante de pago')
                ->modalHeading('Cargar comprobante de pago')
                ->modalDescription('El mismo comprobante, la referencia, la fecha y los bancos se aplican a todas las facturas seleccionadas. Si no indicas montos, cada factura se marca pagada con su propio monto.')
                ->deselectRecordsAfterCompletion()
                ->fillForm(function (Collection $records): array {
                    /** @var OperationAccountsPayable|null $reference */
                    $reference = $records->first();

                    $fill = $reference instanceof OperationAccountsPayable
                        ? self::fillFromRecord($reference, reuseAmounts: false)
                        : self::emptyFill();

                    $fill['payment_receipt_path'] = AccountsPayablePaymentReceiptRegistrar::sharedExistingReceiptPath($records);

                    return $fill;
                })
                ->form(fn (Collection $records): array => self::schema(
                    null,
                    $records->count(),
                    AccountsPayablePaymentReceiptRegistrar::sharedExistingReceiptPath($records) !== null,
                ))
                ->action(function (Collection $records, array $data): void {
                    self::run($records, $data);
                }),
        );
    }

    /**
     * @template T of Action|BulkAction
     *
     * @param  T  $action
     * @return T
     */
    private static function configureModal(Action|BulkAction $action): Action|BulkAction
    {
        return $action
            ->icon('heroicon-m-banknotes')
            ->color('success')
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalIcon('heroicon-m-banknotes')
            ->modalSubmitActionLabel('Guardar comprobante')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                    ])
            )
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cancelar')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                    ])
            )
            ->closeModalByClickingAway(false)
            ->successNotification(null);
    }

    /**
     * @return array<int, mixed>
     */
    private static function schema(?OperationAccountsPayable $record, int $selectedCount, bool $hasExisting = false): array
    {

        return [
            Section::make('Comprobante de pago')
                ->description($selectedCount > 1
                    ? 'Se adjuntará el mismo archivo a las '.$selectedCount.' facturas seleccionadas.'
                    : 'Adjunta la imagen o el PDF del comprobante bancario.')
                ->icon('heroicon-m-paper-clip')
                ->schema([
                    Placeholder::make('payment_receipt_context')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => new HtmlString(
                            '<div style="border-radius:12px;border:1px solid rgba(34,197,94,0.35);background:rgba(34,197,94,0.10);padding:0.75rem 1rem;font-size:0.875rem;line-height:1.5;">'
                            .'Registra la referencia, la fecha, el banco y el monto exactamente como aparecen en el comprobante. Al guardar, las facturas quedan en estatus <strong>Pagada</strong>.'
                            .'</div>'
                        ))
                        ->columnSpanFull(),
                    FileUpload::make('payment_receipt_path')
                        ->label('Archivo del comprobante')
                        ->disk(AccountsPayablePaymentReceiptRegistrar::DISK)
                        ->directory(AccountsPayablePaymentReceiptRegistrar::DIRECTORY)
                        ->visibility('public')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->maxSize(5120)
                        ->downloadable()
                        ->openable()
                        ->imagePreviewHeight('160')
                        ->panelLayout('grid')
                        ->required(fn (): bool => ! $hasExisting)
                        ->helperText('Hasta 5 MB. PDF, JPG, PNG, WebP o GIF.')
                        ->validationMessages([
                            'required' => 'Adjunta el comprobante de pago.',
                        ])
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'fi-helpdesk-ios-section',
                ]),
            Section::make('Datos del pago')
                ->description('Todos los campos viajan junto al comprobante y quedan congelados en cada factura.')
                ->icon('heroicon-m-building-library')
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema([
                            Select::make('payment_status')
                                ->label('Estatus de pago')
                                ->options([
                                    StatusCuentaPorPagar::EnGestion->value => StatusCuentaPorPagar::EnGestion->label(),
                                    StatusCuentaPorPagar::Pagada->value => StatusCuentaPorPagar::Pagada->label(),
                                ])
                                ->default(StatusCuentaPorPagar::Pagada->value)
                                ->selectablePlaceholder(false)
                                ->required()
                                ->helperText('Si cargas un comprobante nuevo, el estatus pasa a Pagada automáticamente.'),
                            TextInput::make('payment_reference')
                                ->label('Referencia de pago')
                                ->prefixIcon('heroicon-m-hashtag')
                                ->maxLength(120)
                                ->required()
                                ->autocomplete(false)
                                ->helperText('Número de referencia o de confirmación del comprobante.')
                                ->validationMessages([
                                    'required' => 'Indica la referencia de pago del comprobante.',
                                ]),
                            DatePicker::make('payment_date')
                                ->label('Fecha del pago')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->default(now()->startOfDay())
                                ->minDate(fn (): mixed => $record?->invoice_date)
                                ->maxDate(now()->endOfDay())
                                ->required()
                                ->helperText('Tal como aparece en el comprobante. No puede ser posterior a hoy.')
                                ->validationMessages([
                                    'required' => 'Indica la fecha del pago.',
                                    'before_or_equal' => 'La fecha del pago no puede ser posterior a hoy.',
                                ]),
                            Select::make('national_bank')
                                ->label('Banco nacional')
                                ->options(BankCatalog::national())
                                ->searchable()
                                ->helperText('Banco emisor o receptor en Venezuela, si aplica.'),
                            Select::make('international_bank')
                                ->label('Banco internacional')
                                ->options(BankCatalog::international())
                                ->searchable()
                                ->helperText('Banco del exterior, si el pago fue en divisas.'),
                            TextInput::make('payment_amount_usd')
                                ->label('Monto del pago en US$')
                                ->prefix('US$')
                                ->numeric()
                                ->minValue(0)
                                ->step('0.01')
                                ->helperText($selectedCount > 1
                                    ? 'Si lo dejas vacío, cada factura usa su propio monto en US$.'
                                    : 'Obligatorio si no registras el monto en bolívares.'),
                            TextInput::make('payment_amount_ves')
                                ->label('Monto del pago en Bs.')
                                ->prefix('Bs.')
                                ->numeric()
                                ->minValue(0)
                                ->step('0.01')
                                ->helperText($selectedCount > 1
                                    ? 'Si lo dejas vacío, cada factura usa su propio monto en bolívares.'
                                    : 'Obligatorio si no registras el monto en US$.'),
                        ]),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'fi-helpdesk-ios-section',
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fillFromRecord(OperationAccountsPayable $record, bool $reuseAmounts = true): array
    {
        $usd = $record->payment_amount_usd;
        $ves = $record->payment_amount_ves;

        if ($reuseAmounts && $usd === null && $ves === null) {
            if ($record->invoice_currency === 'VES') {
                $ves = $record->invoice_amount;
            } else {
                $usd = $record->invoice_amount;
            }
        }

        $status = $record->payment_status === StatusCuentaPorPagar::PendientePorPagar
            ? StatusCuentaPorPagar::Pagada->value
            : ($record->payment_status?->value ?? StatusCuentaPorPagar::Pagada->value);

        return [
            'payment_receipt_path' => $record->payment_receipt_path,
            'payment_status' => $status,
            'payment_reference' => $record->payment_reference,
            'payment_date' => $record->payment_date?->toDateString() ?? now()->toDateString(),
            'national_bank' => $record->national_bank,
            'international_bank' => $record->international_bank,
            'payment_amount_usd' => $reuseAmounts ? $usd : null,
            'payment_amount_ves' => $reuseAmounts ? $ves : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyFill(): array
    {
        return [
            'payment_receipt_path' => null,
            'payment_status' => StatusCuentaPorPagar::Pagada->value,
            'payment_reference' => null,
            'payment_date' => now()->toDateString(),
            'national_bank' => null,
            'international_bank' => null,
            'payment_amount_usd' => null,
            'payment_amount_ves' => null,
        ];
    }

    /**
     * @param  Collection<int, OperationAccountsPayable>  $records
     * @param  array<string, mixed>  $data
     */
    private static function run(Collection $records, array $data): void
    {
        if ($records->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Selecciona al menos una factura')
                ->body('Marca los registros a los que vas a asociar el comprobante.')
                ->send();

            return;
        }

        $actor = Auth::user()?->name ?? 'sistema';

        try {
            $result = AccountsPayablePaymentReceiptRegistrar::applyMany($records, $data, $actor);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->warning()
                ->title('No se pudo guardar el comprobante')
                ->body($exception->getMessage())
                ->send();

            return;
        }

        $count = $result['updated'];

        Notification::make()
            ->success()
            ->title($count === 1 ? 'Comprobante de pago registrado' : 'Comprobantes de pago registrados')
            ->body($count === 1
                ? 'Se guardó el comprobante y los datos de pago de la factura.'
                : 'Se aplicó el mismo comprobante y los mismos datos de pago a '.$count.' facturas.')
            ->send();
    }

    private static function money(mixed $amount, ?string $currency): string
    {
        $symbol = $currency === 'VES' ? 'Bs. ' : 'US$ ';

        return $symbol.number_format((float) $amount, 2, ',', '.');
    }
}

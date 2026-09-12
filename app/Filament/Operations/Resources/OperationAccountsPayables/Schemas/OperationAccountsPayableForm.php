<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Schemas;

use App\Enums\StatusCuentaPorPagar;
use App\Models\BusinessUnit;
use App\Models\OperationAccountsPayable;
use App\Models\Supplier;
use App\Support\BankCatalog;
use App\Support\Operations\AccountsPayablePaymentReceiptRegistrar;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class OperationAccountsPayableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('origin_notice')
                    ->label('')
                    ->content(fn (?OperationAccountsPayable $record): HtmlString => new HtmlString(
                        '<div style="border-radius:12px;border:1px solid rgba(59,130,246,0.35);background:rgba(59,130,246,0.10);padding:0.75rem 1rem;font-size:0.875rem;line-height:1.5;">'
                        .'Esta cuenta por pagar proviene de la orden de servicio <strong>'
                        .e($record?->operationServiceOrder?->order_number ?? '')
                        .'</strong>. Los datos de la factura y del proveedor se editan desde esa orden, con la acción «Actualizar factura»; aquí sólo se registra el pago.'
                        .'</div>'
                    ))
                    ->visible(fn (?OperationAccountsPayable $record): bool => $record?->operation_service_order_id !== null)
                    ->columnSpanFull(),

                Section::make('Datos de la factura')
                    ->description('Documento tal como lo emitió el proveedor.')
                    ->icon('heroicon-o-document-text')
                    ->disabled(fn (?OperationAccountsPayable $record): bool => self::comesFromServiceOrder($record))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('invoice_date')
                            ->label('Fecha de la factura')
                            ->helperText('Fecha de emisión impresa en el documento.')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()->endOfDay())
                            ->required()
                            ->live(onBlur: true),
                        DatePicker::make('invoice_registration_date')
                            ->label('Fecha de registro de la factura')
                            ->helperText('Fecha en la que se recibe y se carga en el sistema.')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->minDate(fn (Get $get): mixed => $get('invoice_date'))
                            ->maxDate(now()->endOfDay())
                            ->required()
                            ->validationMessages([
                                'min_date' => 'La fecha de registro no puede ser anterior a la fecha de la factura.',
                            ]),
                        TextInput::make('invoice_number')
                            ->label('Número de factura')
                            ->maxLength(60)
                            ->required()
                            ->autocomplete(false),
                        TextInput::make('invoice_control_number')
                            ->label('Número de control')
                            ->helperText('Número de control fiscal, si la factura lo trae.')
                            ->maxLength(60)
                            ->autocomplete(false),
                    ]),

                Section::make('Proveedor y unidad de negocio')
                    ->description('El nombre y el RIF quedan guardados en la factura: si mañana cambia la ficha del proveedor, este registro no se altera.')
                    ->icon('heroicon-o-building-storefront')
                    ->disabled(fn (?OperationAccountsPayable $record): bool => self::comesFromServiceOrder($record))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Proveedor del catálogo')
                            ->helperText('Opcional. Al elegirlo se copian el nombre y el RIF.')
                            ->options(fn (): array => Supplier::query()
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getSearchResultsUsing(fn (string $search): array => Supplier::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('razon_social', 'like', "%{$search}%")
                                ->orWhere('rif', 'like', "%{$search}%")
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => Supplier::query()->whereKey($value)->value('name'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $supplier = Supplier::query()->whereKey($state)->first();

                                if ($supplier === null) {
                                    return;
                                }

                                $set('supplier_name', (string) ($supplier->name ?: $supplier->razon_social));
                                $set('supplier_rif', (string) $supplier->rif);
                            }),
                        Select::make('business_unit_id')
                            ->label('Unidad de negocio específica')
                            ->options(fn (): array => BusinessUnit::query()
                                ->where('status', 'ACTIVO')
                                ->orderBy('definition')
                                ->pluck('definition', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('supplier_name')
                            ->label('Nombre del proveedor')
                            ->helperText('Puedes escribirlo a mano si el proveedor no está en el catálogo.')
                            ->maxLength(255)
                            ->required()
                            ->autocomplete(false),
                        TextInput::make('supplier_rif')
                            ->label('RIF')
                            ->placeholder('J-123456789-0')
                            ->maxLength(40)
                            ->required()
                            ->autocomplete(false),
                    ]),

                Section::make('Monto facturado')
                    ->icon('heroicon-o-calculator')
                    ->disabled(fn (?OperationAccountsPayable $record): bool => self::comesFromServiceOrder($record))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('invoice_amount')
                            ->label('Monto de la factura')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->required(),
                        Select::make('invoice_currency')
                            ->label('Moneda de la factura')
                            ->options([
                                'USD' => 'US$ — Dólares',
                                'VES' => 'Bs. — Bolívares',
                            ])
                            ->default('USD')
                            ->selectablePlaceholder(false)
                            ->required(),
                    ]),

                Section::make('Pago')
                    ->description('Al marcar la factura como pagada se exigen la referencia, la fecha, el monto y el comprobante.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('payment_status')
                            ->label('Estatus de pago')
                            ->options(StatusCuentaPorPagar::options())
                            ->default(StatusCuentaPorPagar::default()->value)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('payment_reference')
                            ->label('Referencia de pago')
                            ->maxLength(120)
                            ->autocomplete(false)
                            ->required(fn (Get $get): bool => self::isPaid($get))
                            ->visible(fn (Get $get): bool => ! self::isPending($get)),
                        DatePicker::make('payment_date')
                            ->label('Fecha del pago')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (Get $get): mixed => $get('invoice_date'))
                            ->maxDate(now()->endOfDay())
                            ->required(fn (Get $get): bool => self::isPaid($get))
                            ->visible(fn (Get $get): bool => ! self::isPending($get))
                            ->validationMessages([
                                'min_date' => 'El pago no puede ser anterior a la fecha de la factura.',
                            ]),
                        Select::make('national_bank')
                            ->label('Banco nacional')
                            ->options(BankCatalog::national())
                            ->searchable()
                            ->visible(fn (Get $get): bool => ! self::isPending($get)),
                        Select::make('international_bank')
                            ->label('Banco internacional')
                            ->options(BankCatalog::international())
                            ->searchable()
                            ->visible(fn (Get $get): bool => ! self::isPending($get)),
                        TextInput::make('payment_amount_usd')
                            ->label('Monto del pago en US$')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->visible(fn (Get $get): bool => ! self::isPending($get))
                            ->required(fn (Get $get): bool => self::isPaid($get) && blank($get('payment_amount_ves')))
                            ->helperText('Registra al menos uno de los dos montos.'),
                        TextInput::make('payment_amount_ves')
                            ->label('Monto del pago en Bs.')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->visible(fn (Get $get): bool => ! self::isPending($get))
                            ->required(fn (Get $get): bool => self::isPaid($get) && blank($get('payment_amount_usd'))),
                        FileUpload::make('payment_receipt_path')
                            ->label('Comprobante de pago')
                            ->disk(AccountsPayablePaymentReceiptRegistrar::DISK)
                            ->directory(AccountsPayablePaymentReceiptRegistrar::DIRECTORY)
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->visible(fn (Get $get): bool => ! self::isPending($get))
                            ->required(fn (Get $get): bool => self::isPaid($get))
                            ->helperText('Imagen o PDF del comprobante. Hasta 5 MB.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Observaciones')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? 'system'),
                Hidden::make('updated_by')->dehydrateStateUsing(fn (): string => Auth::user()?->name ?? 'system'),
            ]);
    }

    /**
     * Los datos que manda la orden de servicio no se editan aquí: cambiarlos
     * desincronizaría la factura de la orden que la originó.
     */
    private static function comesFromServiceOrder(?OperationAccountsPayable $record): bool
    {
        return $record?->operation_service_order_id !== null;
    }

    private static function isPaid(Get $get): bool
    {
        return self::status($get) === StatusCuentaPorPagar::Pagada;
    }

    private static function isPending(Get $get): bool
    {
        return self::status($get) === StatusCuentaPorPagar::PendientePorPagar;
    }

    private static function status(Get $get): ?StatusCuentaPorPagar
    {
        return StatusCuentaPorPagar::fromStored($get('payment_status'));
    }
}

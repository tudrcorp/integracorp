<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationServiceOrders\Actions;

use App\Models\BusinessUnit;
use App\Models\OperationServiceOrder;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\ServiceOrderAccountsPayableRegistrar;
use App\Support\Operations\ServiceOrderBulkInvoice;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

final class ServiceOrderBulkInvoiceActions
{
    public static function make(): BulkAction
    {
        return BulkAction::make('uploadInvoiceBulk')
            ->label('Cargar factura')
            ->icon('heroicon-m-receipt-percent')
            ->color('info')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalIcon('heroicon-m-receipt-percent')
            ->modalHeading('Cargar factura de varias órdenes')
            ->modalDescription('Solo puedes hacerlo si todas las órdenes seleccionadas son del mismo proveedor. Revisa la tabla, confirma la sumatoria y adjunta el documento.')
            ->modalSubmitActionLabel('Guardar factura')
            ->modalSubmitAction(
                fn (Action $action): Action => $action
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('info'),
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
            ->deselectRecordsAfterCompletion()
            ->successNotification(null)
            ->fillForm(function (Collection $records): array {
                if (! ServiceOrderBulkInvoice::haveSameSupplier($records)) {
                    return [];
                }

                /** @var OperationServiceOrder $first */
                $first = $records->first();
                $quotedTotal = ServiceOrderBulkInvoice::quotedTotalUsd($records);
                $businessUnits = $records
                    ->map(fn (OperationServiceOrder $record): ?int => ServiceOrderAccountsPayableRegistrar::suggestedBusinessUnitId($record))
                    ->filter()
                    ->unique()
                    ->values();

                $invoiceNumbers = $records
                    ->map(fn (OperationServiceOrder $record): string => trim((string) $record->invoice_number))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'invoice_number' => $invoiceNumbers->count() === 1 ? $invoiceNumbers->first() : null,
                    'invoice_control_number' => $first->invoice_control_number,
                    'invoice_date' => $first->invoice_date,
                    'invoice_registration_date' => now()->startOfDay(),
                    'invoice_amount_usd' => $quotedTotal > 0 ? $quotedTotal : null,
                    'invoice_amount_ves' => null,
                    'invoice_file_path' => null,
                    'payable_supplier_name' => ServiceOrderAccountsPayableRegistrar::suggestedSupplierName($first),
                    'payable_supplier_rif' => ServiceOrderAccountsPayableRegistrar::suggestedSupplierRif($first),
                    'payable_business_unit_id' => $businessUnits->count() === 1 ? $businessUnits->first() : null,
                ];
            })
            ->form(fn (Collection $records): array => ServiceOrderBulkInvoice::haveSameSupplier($records)
                ? self::invoiceForm($records)
                : self::mismatchForm($records))
            ->action(function (Collection $records, array $data): void {
                if ($records->isEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title('Selecciona al menos una orden')
                        ->body('Marca las órdenes del mismo proveedor a las que corresponde la factura.')
                        ->send();

                    return;
                }

                $actor = Auth::user()?->name ?? 'sistema';

                try {
                    $result = ServiceOrderBulkInvoice::apply($records, $data, $actor);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->warning()
                        ->title('No se pudo guardar la factura')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $count = $result['updated'];

                if ($result['payments_preserved'] > 0) {
                    Notification::make()
                        ->warning()
                        ->persistent()
                        ->title('Factura actualizada; se conservó el pago')
                        ->body($result['payments_preserved'] === 1
                            ? 'Una de las cuentas por pagar ya tenía un pago registrado: se actualizó la factura y se respetaron referencia, fecha y montos del pago.'
                            : $result['payments_preserved'].' cuentas por pagar ya tenían un pago registrado y se conservó.')
                        ->send();
                }

                $difference = $result['quote_difference_usd'];

                if ($difference !== null && abs($difference) >= 0.01) {
                    Notification::make()
                        ->warning()
                        ->persistent()
                        ->title('Factura registrada con diferencia')
                        ->body('Se facturaron '.$count.' órdenes, pero el total de la factura difiere de la sumatoria cotizada en '
                            .ServiceOrderBulkInvoice::money(abs($difference))
                            .' ('.($difference > 0 ? 'por encima' : 'por debajo').'). Verifica con el proveedor si corresponde.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title($count === 1 ? 'Factura registrada' : 'Factura registrada en '.$count.' órdenes')
                    ->body($count === 1
                        ? 'La orden pasó a estatus administrativo «Facturado» y quedó en Cuentas por pagar.'
                        : 'Las '.$count.' órdenes pasaron a «Facturado». El total de la factura se prorrateó según el monto cotizado de cada una y se generó o actualizó su cuenta por pagar.')
                    ->send();
            });
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $records
     * @return array<int, mixed>
     */
    private static function mismatchForm(Collection $records): array
    {
        return [
            Placeholder::make('supplier_mismatch')
                ->hiddenLabel()
                ->content(fn (): HtmlString => new HtmlString(
                    '<div style="border-radius:12px;border:1px solid rgba(239,68,68,0.4);background:rgba(239,68,68,0.12);padding:0.9rem 1rem;font-size:0.875rem;line-height:1.5;">'
                    .'<strong>No se puede cargar la factura.</strong> '
                    .e(ServiceOrderBulkInvoice::mismatchMessage($records))
                    .'</div>'
                ))
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $records
     * @return array<int, mixed>
     */
    private static function invoiceForm(Collection $records): array
    {
        $quotedTotal = ServiceOrderBulkInvoice::quotedTotalUsd($records);
        $supplierName = ServiceOrderBulkInvoice::supplierDisplayName($records->first());

        return [
            Section::make('Órdenes seleccionadas')
                ->description($records->count().' órdenes de '.$supplierName.' · sumatoria cotizada '.ServiceOrderBulkInvoice::money($quotedTotal).'. El total de la factura se reparte entre estas filas.')
                ->icon('heroicon-m-queue-list')
                ->schema([
                    Placeholder::make('selected_orders_table')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => ServiceOrderBulkInvoice::renderSelectedTable($records))
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'fi-helpdesk-ios-section',
                ]),
            Section::make('Datos de la factura')
                ->description('Número, control, fechas y documento tal como los emitió el proveedor. El monto debe coincidir con el total impreso; se sugiere la sumatoria de las órdenes.')
                ->icon('heroicon-m-document-currency-dollar')
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema([
                            TextInput::make('invoice_number')
                                ->label('N° de factura')
                                ->prefixIcon('heroicon-m-hashtag')
                                ->placeholder('Ej. 00012345')
                                ->required()
                                ->maxLength(60)
                                ->helperText('Tal como aparece en el documento del proveedor. Queda en todas las órdenes seleccionadas.')
                                ->validationMessages([
                                    'required' => 'Indica el número de la factura.',
                                    'max' => 'El número de factura no puede superar los 60 caracteres.',
                                ]),
                            TextInput::make('invoice_control_number')
                                ->label('N° de control')
                                ->prefixIcon('heroicon-m-shield-check')
                                ->placeholder('Ej. 00-0012345')
                                ->maxLength(60)
                                ->helperText('Número fiscal impreso por la imprenta autorizada. Déjalo vacío si la factura no lo trae.')
                                ->validationMessages([
                                    'max' => 'El número de control no puede superar los 60 caracteres.',
                                ]),
                            DatePicker::make('invoice_date')
                                ->label('Fecha de emisión de la factura')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->maxDate(now())
                                ->required()
                                ->live(onBlur: true)
                                ->helperText('No puede ser posterior a hoy.')
                                ->validationMessages([
                                    'required' => 'Indica la fecha de emisión de la factura.',
                                    'before_or_equal' => 'La fecha de emisión no puede ser posterior a hoy.',
                                ]),
                            DatePicker::make('invoice_registration_date')
                                ->label('Fecha de registro de la factura')
                                ->prefixIcon('heroicon-m-inbox-arrow-down')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->default(now()->startOfDay())
                                ->minDate(fn (Get $get) => $get('invoice_date') ?: null)
                                ->maxDate(now())
                                ->required()
                                ->helperText('Día en que Operaciones recibe y carga la factura. Se sugiere hoy.')
                                ->validationMessages([
                                    'required' => 'Indica la fecha en que registras la factura.',
                                    'after_or_equal' => 'La fecha de registro no puede ser anterior a la fecha de emisión de la factura.',
                                    'before_or_equal' => 'La fecha de registro no puede ser posterior a hoy.',
                                ]),
                            TextInput::make('invoice_amount_usd')
                                ->label('Monto total de la factura en US$')
                                ->prefix('US$')
                                ->placeholder('0,00')
                                ->numeric()
                                ->minValue(0)
                                ->requiredWithout('invoice_amount_ves')
                                ->helperText('Se sugiere la sumatoria cotizada ('.ServiceOrderBulkInvoice::money($quotedTotal).'). Se prorratea entre las órdenes.')
                                ->validationMessages([
                                    'required_without' => 'Indica el monto en US$ o, en su defecto, el monto en bolívares.',
                                    'numeric' => 'El monto en US$ debe ser un número.',
                                    'min' => 'El monto en US$ no puede ser negativo.',
                                ]),
                            TextInput::make('invoice_amount_ves')
                                ->label('Monto total de la factura en Bs.')
                                ->prefix('Bs.')
                                ->placeholder('0,00')
                                ->numeric()
                                ->minValue(0)
                                ->requiredWithout('invoice_amount_usd')
                                ->helperText('Opcional si ya indicaste el monto en US$.')
                                ->validationMessages([
                                    'required_without' => 'Indica el monto en bolívares o, en su defecto, el monto en US$.',
                                    'numeric' => 'El monto en Bs. debe ser un número.',
                                    'min' => 'El monto en Bs. no puede ser negativo.',
                                ]),
                        ]),
                    FileUpload::make('invoice_file_path')
                        ->label('Documento de la factura')
                        ->disk('public')
                        ->directory('operation-service-orders/invoices')
                        ->visibility('public')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(4096)
                        ->required()
                        ->downloadable()
                        ->openable()
                        ->helperText('El mismo archivo queda asociado a todas las órdenes. PDF, JPG, PNG o WebP. Máximo 4 MB.')
                        ->validationMessages([
                            'required' => 'Debes adjuntar el documento de la factura.',
                        ])
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'fi-helpdesk-ios-section',
                ]),
            Section::make('Datos para cuentas por pagar')
                ->description('Se genera o actualiza una cuenta por pagar por cada orden, con su prorrateo. Nombre y RIF quedan congelados.')
                ->icon('heroicon-m-banknotes')
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema([
                            TextInput::make('payable_supplier_name')
                                ->label('Nombre del proveedor')
                                ->prefixIcon('heroicon-m-building-storefront')
                                ->required()
                                ->maxLength(255)
                                ->validationMessages([
                                    'required' => 'Indica el nombre del proveedor que emite la factura.',
                                ]),
                            TextInput::make('payable_supplier_rif')
                                ->label('RIF del proveedor')
                                ->prefixIcon('heroicon-m-identification')
                                ->placeholder('J-123456789-0')
                                ->required()
                                ->maxLength(40)
                                ->validationMessages([
                                    'required' => 'Indica el RIF del proveedor; sin él la cuenta por pagar queda incompleta.',
                                ]),
                            Select::make('payable_business_unit_id')
                                ->label('Unidad de negocio (si alguna orden no la tiene)')
                                ->prefixIcon('heroicon-m-squares-2x2')
                                ->options(fn (): array => BusinessUnit::query()
                                    ->where('status', 'ACTIVO')
                                    ->orderBy('definition')
                                    ->pluck('definition', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->helperText('Si cada orden ya tiene unidad de negocio en la coordinación, se respeta. Este campo cubre las que no la traen.')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'fi-helpdesk-ios-section',
                ]),
        ];
    }
}

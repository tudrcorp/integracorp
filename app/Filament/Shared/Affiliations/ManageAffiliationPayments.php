<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\Affiliation;
use App\Models\Sale;
use App\Services\SaleInvoicePdfService;
use App\Support\Affiliations\PaymentVoucherFile;
use App\Support\Filament\RecordPageHeader;
use App\Support\Sales\InvoiceVesLineAmounts;
use App\Support\Sales\PaymentSaleResolver;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Pagos realizados de una afiliación, solo lectura: lista detallada y
 * comprobante (voucher) de cada pago para verlo o descargarlo.
 *
 * Por defecto es la individual (`paid_memberships`), que comparten Negocios y
 * Administración; {@see ManageAffiliationCorporatePayments} la adapta a las
 * corporativas cambiando solo la relación y el encabezado. Cada panel la
 * registra con su propio recurso. Aprobar pagos sigue en Administración.
 */
abstract class ManageAffiliationPayments extends ManageRelatedRecords
{
    protected static string $relationship = 'paid_memberships';

    protected static ?string $title = 'Pagos realizados';

    protected static ?string $breadcrumb = 'Pagos realizados';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** Ventas de la afiliación, cargadas una vez por petición (no se serializa). */
    protected ?PaymentSaleResolver $saleResolver = null;

    /** Pagos aprobados que se pueden facturar desde esta página. */
    public const INVOICEABLE_STATUS = 'APROBADO';

    /**
     * Facturar es tarea de Administración: solo sus páginas lo encienden.
     */
    public static function allowsInvoicing(): bool
    {
        return false;
    }

    /**
     * @return array{status: string, sale: Sale|null}
     */
    public function saleForPayment(Model $payment): array
    {
        $this->saleResolver ??= new PaymentSaleResolver($this->getOwnerRecord()->getAttribute('code'));

        return $this->saleResolver->resolve($payment);
    }

    /**
     * Mismo acceso que el recurso de afiliaciones del panel. Sin esto la página
     * solo consultaría la política de PaidMembership, que no existe, y dejaría
     * entrar a cualquier usuario autenticado del panel con la URL.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return static::getResource()::canAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pagos realizados';
    }

    /**
     * Mismo encabezado que la ficha de la afiliación ({@see RecordPageHeader}),
     * con el resumen de sus pagos en la fila de datos.
     */
    public function getHeading(): string|Htmlable
    {
        $owner = $this->getOwnerRecord();
        $summary = static::paymentsSummary($owner);
        $header = static::ownerHeader($owner);

        return RecordPageHeader::render(
            eyebrow: 'Pagos realizados · '.($owner->getAttribute('code') ?? 'Sin código'),
            title: $header['title'],
            status: RecordPageHeader::statusFor($owner->getAttribute('status')),
            chips: [
                ...$header['chips'],
                $summary['pending'] > 0 ? RecordPageHeader::tag($summary['pending'].' '.($summary['pending'] === 1 ? 'pago pendiente' : 'pagos pendientes'), RecordPageHeader::TONE_WARNING) : null,
            ],
            facts: [
                ...$header['facts'],
                'Pagos registrados' => (string) $summary['count'],
                'Aprobados' => $summary['count'] > 0 ? (string) $summary['approved'] : null,
                'Total aprobado US$' => RecordPageHeader::money($summary['approved_usd'], hideZero: true),
                'Total aprobado Bs.' => $summary['approved_ves'] > 0 ? InvoiceVesLineAmounts::format($summary['approved_ves']) : null,
                'Último pago' => $summary['last_at'],
            ],
        );
    }

    /**
     * Identidad del dueño de los pagos en el encabezado (afiliación individual).
     *
     * @return array{title: string, chips: list<array{label: string, tone: string}|null>, facts: array<string, string|null>}
     */
    protected static function ownerHeader(Model $owner): array
    {
        /** @var Affiliation $owner */
        return [
            'title' => (string) ($owner->full_name_ti ?: 'Sin nombre de titular'),
            'chips' => [
                filled($owner->plan?->description) ? RecordPageHeader::tag((string) $owner->plan->description) : null,
                filled($owner->payment_frequency) ? RecordPageHeader::tag('Pago '.mb_strtolower((string) $owner->payment_frequency), RecordPageHeader::TONE_NEUTRAL) : null,
            ],
            'facts' => [
                'Cédula titular' => $owner->nro_identificacion_ti,
            ],
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * Resumen de los pagos en una sola consulta agregada.
     *
     * @return array{count: int, approved: int, pending: int, approved_usd: float, approved_ves: float, last_at: string|null}
     */
    public static function paymentsSummary(Model $owner): array
    {
        $row = $owner->{static::getRelationshipName()}()
            ->getQuery()
            ->reorder()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN UPPER(status) IN ('APROBADO', 'PAGADO') THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN UPPER(status) = 'PENDIENTE' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN UPPER(status) IN ('APROBADO', 'PAGADO') THEN COALESCE(pay_amount_usd, 0) ELSE 0 END) as approved_usd")
            ->selectRaw("SUM(CASE WHEN UPPER(status) IN ('APROBADO', 'PAGADO') THEN COALESCE(pay_amount_ves, 0) ELSE 0 END) as approved_ves")
            ->selectRaw('MAX(created_at) as last_at')
            ->toBase()
            ->first();

        return [
            'count' => (int) ($row->total_count ?? 0),
            'approved' => (int) ($row->approved_count ?? 0),
            'pending' => (int) ($row->pending_count ?? 0),
            'approved_usd' => round((float) ($row->approved_usd ?? 0), 2),
            'approved_ves' => round((float) ($row->approved_ves ?? 0), 2),
            'last_at' => filled($row->last_at ?? null) ? Carbon::parse($row->last_at)->format('d/m/Y') : null,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToAffiliation')
                ->label('Volver a la afiliación')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('view', ['record' => $this->getOwnerRecord()])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Historial de pagos')
            ->description('Todos los pagos cargados para esta afiliación, del más reciente al más antiguo. Use los tres puntos de cada fila para ver o descargar el comprobante.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['plan:id,description']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y h:i a')
                    ->description(fn (Model $record): ?string => filled($record->created_by) ? 'Por '.$record->created_by : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => self::statusColor($state))
                    ->icon(fn (?string $state): Heroicon => self::statusIcon($state))
                    ->description(fn (Model $record): ?string => filled($record->aproved_by) ? 'Aprobó: '.$record->aproved_by : null),
                TextColumn::make('payment_date')
                    ->label('Período pagado')
                    ->formatStateUsing(fn (Model $record): string => ($record->payment_date ?: '—').' → '.($record->prox_payment_date ?: '—'))
                    ->description(fn (Model $record): ?string => filled($record->payment_frequency) ? (string) $record->payment_frequency : null)
                    ->placeholder('—'),
                TextColumn::make('pay_amount_usd')
                    ->label('Pagado en US$')
                    ->money('USD')
                    ->alignEnd()
                    ->weight('semibold')
                    ->description(fn (Model $record): ?string => self::methodLine($record->payment_method_usd, $record->bank_usd))
                    ->placeholder('—'),
                TextColumn::make('pay_amount_ves')
                    ->label('Pagado en Bs.')
                    ->formatStateUsing(fn (mixed $state): string => (float) $state > 0 ? InvoiceVesLineAmounts::format($state) : '—')
                    ->alignEnd()
                    ->weight('semibold')
                    ->description(fn (Model $record): ?string => self::methodLine($record->payment_method_ves, $record->bank_ves))
                    ->placeholder('—'),
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->alignEnd()
                    ->placeholder('—'),
                TextColumn::make('total_amount')
                    ->label('Total de la cuota')
                    ->money('USD')
                    ->alignEnd()
                    ->placeholder('—'),
                TextColumn::make('reference_payment_usd')
                    ->label('Referencia')
                    ->state(fn (Model $record): ?string => self::references($record))
                    ->searchable(['reference_payment_usd', 'reference_payment_ves'])
                    ->copyable()
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('vouchers')
                    ->label('Comprobante')
                    ->state(fn (Model $record): string => self::voucherSummary($record))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sin comprobante' ? 'gray' : 'info'),
                TextColumn::make('invoice_number')
                    ->label('Recibo')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('date_payment_voucher')
                    ->label('Fecha del comprobante')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('observations_payment')
                    ->label('Observaciones')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'APROBADO' => 'Aprobado',
                        'PENDIENTE' => 'Pendiente',
                        'PAGADO' => 'Pagado',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::generateInvoiceAction(),
                    self::viewVoucherAction(PaymentVoucherFile::CURRENCY_USD),
                    self::downloadVoucherAction(PaymentVoucherFile::CURRENCY_USD),
                    self::viewVoucherAction(PaymentVoucherFile::CURRENCY_VES),
                    self::downloadVoucherAction(PaymentVoucherFile::CURRENCY_VES),
                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->tooltip('Comprobante del pago'),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([])
            ->headerActions([])
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('Esta afiliación no tiene pagos cargados')
            ->emptyStateDescription('Cuando se registre un pago aparecerá aquí con su comprobante.')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * Factura en bolívares de un pago aprobado: cuota del pago (US$) × tasa BCV
     * que escribe el analista. Se registra en la venta del pago, así Ventas y
     * Pagos ven la misma factura; si ya tiene una, se regenera conservando número
     * y fecha.
     */
    public static function generateInvoiceAction(): Action
    {
        return Action::make('generateInvoice')
            ->label('Generar Factura')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('info')
            ->visible(fn (Model $record, ManageAffiliationPayments $livewire): bool => $livewire::allowsInvoicing()
                && Str::upper(trim((string) $record->getAttribute('status'))) === self::INVOICEABLE_STATUS)
            ->disabled(fn (Model $record, ManageAffiliationPayments $livewire): bool => $livewire->saleForPayment($record)['sale'] === null)
            ->tooltip(fn (Model $record, ManageAffiliationPayments $livewire): ?string => PaymentSaleResolver::reasonLabel($livewire->saleForPayment($record)['status']))
            ->modalIcon(Heroicon::OutlinedPrinter)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(fn (Model $record, ManageAffiliationPayments $livewire): string => filled($livewire->saleForPayment($record)['sale']?->invoice_generated)
                ? 'Regenerar factura N° '.$livewire->saleForPayment($record)['sale']->invoice_generated
                : 'Generar factura del pago')
            ->modalDescription(fn (Model $record, ManageAffiliationPayments $livewire): string => filled($livewire->saleForPayment($record)['sale']?->invoice_generated)
                ? 'Este pago ya tiene factura. Se vuelve a generar con la tasa indicada; el número y la fecha de emisión no cambian y el PDF anterior queda como respaldo.'
                : 'El monto en bolívares es la cuota del pago por la tasa BCV que indique. La factura queda registrada en la venta de este pago.')
            ->modalSubmitActionLabel(fn (Model $record, ManageAffiliationPayments $livewire): string => filled($livewire->saleForPayment($record)['sale']?->invoice_generated)
                ? 'Regenerar y descargar'
                : 'Generar y descargar')
            ->fillForm(function (Model $record, ManageAffiliationPayments $livewire): array {
                $sale = $livewire->saleForPayment($record)['sale'];
                $previous = $sale !== null && filled($sale->invoice_generated)
                    ? app(SaleInvoicePdfService::class)->previousInvoiceInput($sale)
                    : null;
                $party = $previous['billing_party'] ?? [];

                return [
                    'date' => $previous['date'] ?? now()->format('d/m/Y'),
                    'tasa_bcv' => $previous['tasa_bcv'] ?? null,
                    'invoice_in_name_of' => $previous['invoice_in_name_of'] ?? 'titular',
                    'custom_full_name' => $party['full_name_ti'] ?? null,
                    'custom_ci_rif' => $party['ci_rif_ti'] ?? null,
                    'custom_address' => $party['address_ti'] ?? null,
                    'custom_phone' => $party['phone_ti'] ?? null,
                    'custom_email' => $party['email_ti'] ?? null,
                ];
            })
            ->schema(function (Model $record, ManageAffiliationPayments $livewire): array {
                $sale = $livewire->saleForPayment($record)['sale'];
                $alreadyInvoiced = filled($sale?->invoice_generated);
                $baseUsd = round((float) $record->getAttribute('total_amount'), 2);

                return [
                    Section::make('Pago a facturar')
                        ->schema([
                            TextEntry::make('payment_period')
                                ->label('Período pagado')
                                ->state(($record->getAttribute('payment_date') ?: '—').' → '.($record->getAttribute('prox_payment_date') ?: '—')),
                            TextEntry::make('payment_frequency_label')
                                ->label('Frecuencia')
                                ->state($record->getAttribute('payment_frequency') ?: '—'),
                            TextEntry::make('payment_receipt')
                                ->label('Recibo')
                                ->state($record->getAttribute('invoice_number') ?: '—'),
                            TextEntry::make('payment_method_label')
                                ->label('Método y banco')
                                ->state(self::methodLine($record->getAttribute('payment_method'), $record->getAttribute('bank_ves') ?: $record->getAttribute('bank_usd')) ?? '—'),
                            TextEntry::make('payment_reference')
                                ->label('Referencia')
                                ->state(self::references($record) ?? '—'),
                            TextEntry::make('payment_base_usd')
                                ->label('Cuota del pago')
                                ->state('US$ '.number_format($baseUsd, 2, ',', '.'))
                                ->weight('semibold'),
                        ])
                        ->columns(3),
                    Section::make('Factura')
                        ->schema([
                            ...($alreadyInvoiced
                                ? [
                                    TextEntry::make('existing_invoice')
                                        ->label('Nro. de factura')
                                        ->state((string) $sale->invoice_generated),
                                    TextEntry::make('existing_date')
                                        ->label('Fecha de emisión')
                                        ->state(fn (Get $get): string => (string) ($get('date') ?: '—')),
                                ]
                                : [
                                    TextInput::make('invoice_number')
                                        ->label('Nro. de factura')
                                        ->required()
                                        ->maxLength(100)
                                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                            $number = trim((string) $value);

                                            if (Sale::query()->where('invoice_generated', $number)->exists()
                                                || is_file(SaleInvoicePdfService::pdfPath($number))) {
                                                $fail('Ese número de factura ya fue emitido. Verifique el correlativo.');
                                            }
                                        }),
                                    DatePicker::make('date')
                                        ->label('Fecha de emisión')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->format('d/m/Y')
                                        ->required(),
                                ]),
                            TextInput::make('tasa_bcv')
                                ->label('Tasa BCV')
                                ->helperText('Bolívares por dólar del día de la factura.')
                                ->numeric()
                                ->minValue(0.0001)
                                ->required()
                                ->suffix('Bs/US$')
                                ->live(debounce: 400),
                            TextEntry::make('amount_to_invoice')
                                ->label('Monto a facturar')
                                ->state(fn (Get $get): string => is_numeric($get('tasa_bcv')) && (float) $get('tasa_bcv') > 0
                                    ? InvoiceVesLineAmounts::format(round($baseUsd * (float) $get('tasa_bcv'), 2))
                                    : 'Indique la tasa BCV')
                                ->helperText('US$ '.number_format($baseUsd, 2, ',', '.').' × tasa BCV')
                                ->weight('bold')
                                ->color('success'),
                        ])
                        ->columns(2),
                    Section::make('Facturar a nombre de')
                        ->schema([
                            Radio::make('invoice_in_name_of')
                                ->label('A nombre de quién se emite la factura')
                                ->options([
                                    'titular' => 'A nombre del Titular',
                                    'tomador' => 'A nombre del Tomador',
                                    'custom' => 'Factura personalizada',
                                ])
                                ->live()
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('custom_full_name')
                                ->label('Nombre / Razón social')
                                ->required(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom')
                                ->visible(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom'),
                            TextInput::make('custom_ci_rif')
                                ->label('CI / RIF')
                                ->required(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom')
                                ->visible(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom'),
                            TextInput::make('custom_address')
                                ->label('Dirección')
                                ->required(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom')
                                ->visible(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom')
                                ->columnSpanFull(),
                            TextInput::make('custom_phone')
                                ->label('Teléfono')
                                ->required(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom')
                                ->visible(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom'),
                            TextInput::make('custom_email')
                                ->label('Correo')
                                ->email()
                                ->required(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom')
                                ->visible(fn (Get $get): bool => $get('invoice_in_name_of') === 'custom'),
                        ])
                        ->columns(2),
                ];
            })
            ->action(fn (Model $record, array $data, ManageAffiliationPayments $livewire): ?BinaryFileResponse => self::invoicePayment($record, $data, $livewire));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function invoicePayment(Model $payment, array $data, ManageAffiliationPayments $livewire): ?BinaryFileResponse
    {
        $resolved = $livewire->saleForPayment($payment);
        $sale = $resolved['sale'];

        if ($sale === null) {
            Notification::make()
                ->title('No se puede facturar este pago')
                ->body(PaymentSaleResolver::reasonLabel($resolved['status']))
                ->danger()
                ->send();

            return null;
        }

        $input = [
            ...$data,
            'invoice_base_usd' => round((float) $payment->getAttribute('total_amount'), 2),
            'source_payment' => ['table' => $payment->getTable(), 'id' => $payment->getKey()],
        ];

        $lock = Cache::lock('sale-invoice:'.$sale->getKey(), 60);

        if (! $lock->get()) {
            Notification::make()
                ->title('Otra persona está facturando esta venta')
                ->body('Espere unos segundos y vuelva a intentarlo.')
                ->warning()
                ->send();

            return null;
        }

        try {
            $sale->refresh();
            $regenerating = filled($sale->invoice_generated);
            $service = app(SaleInvoicePdfService::class);

            $path = $regenerating
                ? $service->regenerate($sale, $input)['path']
                : $service->generate($sale, $input);

            SecurityAudit::log($regenerating ? 'AUDIT_ADMIN_PAYMENT_INVOICE_REGENERATED' : 'AUDIT_ADMIN_PAYMENT_INVOICE_GENERATED', 'affiliations.payments.invoice', [
                'payment_table' => $payment->getTable(),
                'payment_id' => $payment->getKey(),
                'sale_id' => $sale->getKey(),
                'invoice_number' => $sale->invoice_generated,
                'tasa_bcv' => $sale->invoice_snapshot['tasa_bcv'] ?? null,
                'base_usd' => $sale->invoice_snapshot['base_usd'] ?? null,
                'total_ves' => $sale->invoice_snapshot['total_ves'] ?? null,
            ], Auth::user());

            Notification::make()
                ->title($regenerating ? 'Factura regenerada' : 'Factura generada')
                ->body('Factura N° '.$sale->invoice_generated.' por '.InvoiceVesLineAmounts::format($sale->invoice_snapshot['total_ves'] ?? null).'. Se está descargando.')
                ->success()
                ->send();

            return response()->download($path);
        } catch (Throwable $exception) {
            SecurityAudit::log('AUDIT_ADMIN_PAYMENT_INVOICE_FAILED', 'affiliations.payments.invoice', [
                'payment_table' => $payment->getTable(),
                'payment_id' => $payment->getKey(),
                'sale_id' => $sale->getKey(),
                'error_message' => $exception->getMessage(),
                'error_class' => $exception::class,
            ], Auth::user());

            Log::error('ManageAffiliationPayments: no se pudo generar la factura del pago', [
                'payment_id' => $payment->getKey(),
                'sale_id' => $sale->getKey(),
                'message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->title('No se pudo generar la factura')
                ->body($exception instanceof RuntimeException ? $exception->getMessage() : 'Ocurrió un error al generar el PDF. Intente de nuevo; si persiste, avise a Sistemas.')
                ->danger()
                ->send();

            return null;
        } finally {
            $lock->release();
        }
    }

    public static function viewVoucherAction(string $currency): Action
    {
        $label = PaymentVoucherFile::currencyLabel($currency);

        return Action::make('viewVoucher'.ucfirst($currency))
            ->label('Ver comprobante '.$label)
            ->icon(Heroicon::OutlinedEye)
            ->color('info')
            ->visible(fn (Model $record): bool => PaymentVoucherFile::path($record, $currency) !== null)
            ->modalHeading(fn (Model $record): string => 'Comprobante en '.$label.' · pago del '.($record->payment_date ?: $record->created_at?->format('d/m/Y')))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(function (Model $record) use ($currency): View {
                $path = PaymentVoucherFile::path($record, $currency);
                $exists = PaymentVoucherFile::exists($path);

                self::audit('AUDIT_AFFILIATION_PAYMENT_VOUCHER_VIEWED', $record, $currency, $exists);

                return view('filament.shared.affiliations.payment-voucher-preview', [
                    'exists' => $exists,
                    'kind' => PaymentVoucherFile::kind($path),
                    'url' => $exists ? PaymentVoucherFile::url((string) $path) : null,
                    'name' => basename((string) $path),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('downloadFromPreview', arguments: ['download' => true])
                    ->label('Descargar')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary'),
            ])
            ->action(function (Model $record, array $arguments, ManageRelatedRecords $livewire) use ($currency): ?StreamedResponse {
                return ($arguments['download'] ?? false) ? self::download($record, $currency, $livewire->getOwnerRecord()->getAttribute('code')) : null;
            });
    }

    public static function downloadVoucherAction(string $currency): Action
    {
        return Action::make('downloadVoucher'.ucfirst($currency))
            ->label('Descargar comprobante '.PaymentVoucherFile::currencyLabel($currency))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->visible(fn (Model $record): bool => PaymentVoucherFile::path($record, $currency) !== null)
            ->action(fn (Model $record, ManageRelatedRecords $livewire): ?StreamedResponse => self::download($record, $currency, $livewire->getOwnerRecord()->getAttribute('code')));
    }

    public static function download(Model $record, string $currency, ?string $affiliationCode = null): ?StreamedResponse
    {
        $path = PaymentVoucherFile::path($record, $currency);

        if (! PaymentVoucherFile::exists($path)) {
            self::audit('AUDIT_AFFILIATION_PAYMENT_VOUCHER_MISSING', $record, $currency, false);

            Notification::make()
                ->title('No se encontró el comprobante')
                ->body('El archivo de este pago ya no está en el servidor. Solicite al área de Administración que lo vuelva a cargar.')
                ->danger()
                ->send();

            return null;
        }

        self::audit('AUDIT_AFFILIATION_PAYMENT_VOUCHER_DOWNLOADED', $record, $currency, true);

        return Storage::disk(PaymentVoucherFile::DISK)->download(
            (string) $path,
            PaymentVoucherFile::downloadName($record, $currency, (string) $path, $affiliationCode),
        );
    }

    public static function statusColor(?string $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'APROBADO', 'PAGADO' => 'success',
            'PENDIENTE' => 'warning',
            'RECHAZADO', 'ANULADO' => 'danger',
            default => 'gray',
        };
    }

    private static function statusIcon(?string $status): Heroicon
    {
        return match (self::statusColor($status)) {
            'success' => Heroicon::OutlinedCheckCircle,
            'warning' => Heroicon::OutlinedClock,
            'danger' => Heroicon::OutlinedXCircle,
            default => Heroicon::OutlinedQuestionMarkCircle,
        };
    }

    private static function methodLine(?string $method, ?string $bank): ?string
    {
        $parts = array_filter([trim((string) $method), trim((string) $bank)], fn (string $part): bool => $part !== '' && strtoupper($part) !== 'N/A');

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public static function references(Model $record): ?string
    {
        $references = array_filter([
            self::cleanReference($record->reference_payment_usd) !== null ? 'US$: '.self::cleanReference($record->reference_payment_usd) : null,
            self::cleanReference($record->reference_payment_ves) !== null ? 'Bs.: '.self::cleanReference($record->reference_payment_ves) : null,
        ]);

        return $references === [] ? null : implode(' · ', $references);
    }

    public static function voucherSummary(Model $record): string
    {
        $currencies = array_filter([
            PaymentVoucherFile::path($record, PaymentVoucherFile::CURRENCY_USD) !== null ? 'US$' : null,
            PaymentVoucherFile::path($record, PaymentVoucherFile::CURRENCY_VES) !== null ? 'Bs.' : null,
        ]);

        return $currencies === [] ? 'Sin comprobante' : 'Cargado ('.implode(' y ', $currencies).')';
    }

    private static function cleanReference(?string $reference): ?string
    {
        $reference = trim((string) $reference);

        return $reference === '' || strtoupper($reference) === 'N/A' ? null : $reference;
    }

    private static function audit(string $event, Model $record, string $currency, bool $fileExists): void
    {
        SecurityAudit::log($event, 'affiliations.payments.voucher', [
            'payment_table' => $record->getTable(),
            'payment_id' => $record->getKey(),
            'affiliation_id' => $record->getAttribute('affiliation_id') ?? $record->getAttribute('affiliation_corporate_id'),
            'currency' => $currency,
            'file_exists' => $fileExists,
        ], Auth::user());
    }
}

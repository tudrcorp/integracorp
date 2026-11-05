<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCashPayments\Tables;

use App\Models\OperationCashPayment;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\AccountsPayablePaymentReceiptRegistrar;
use App\Support\Operations\CashPayments\CashPaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * Tabla de pagos de contado (Operaciones y Administración): cada pago con su factura,
 * proveedor, banco, monto y comprobante; los reversados quedan visibles con su razón.
 */
final class OperationCashPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('serviceOrder:id,order_number'))
            ->paginated([25, 50, 100])
            ->striped()
            ->searchPlaceholder('Referencia, factura, recibo o proveedor')
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Fecha del pago')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('serviceOrder.order_number')
                    ->label('Orden')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->description(fn (OperationCashPayment $record): string => $record->documentLabel()),
                TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->description(fn (OperationCashPayment $record): ?string => $record->supplier_rif)
                    ->searchable(['supplier_name', 'supplier_rif', 'invoice_number', 'invoice_receipt_number', 'payment_reference'])
                    ->wrap(),
                TextColumn::make('payment_amount')
                    ->label('Monto pagado')
                    ->formatStateUsing(fn (OperationCashPayment $record): string => CashPaymentService::money((float) $record->payment_amount, (string) $record->payment_currency))
                    ->description(fn (OperationCashPayment $record): ?string => $record->bcv_rate !== null
                        ? 'Tasa '.number_format((float) $record->bcv_rate, 4, ',', '.').' · equivale a '.CashPaymentService::money((float) $record->amount_in_invoice_currency, (string) $record->invoice_currency)
                        : null)
                    ->alignEnd(),
                TextColumn::make('bank')
                    ->label('Banco')
                    ->description(fn (OperationCashPayment $record): string => 'Ref. '.$record->payment_reference)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (string $state): string => $state === OperationCashPayment::STATUS_REVERSED ? 'danger' : 'success')
                    ->description(fn (OperationCashPayment $record): ?string => $record->isActive() ? null : 'Por '.$record->reversed_by_name.' · '.$record->reversed_at?->format('d/m/Y')),
                TextColumn::make('registered_by_name')
                    ->label('Registrado por')
                    ->description(fn (OperationCashPayment $record): string => (string) $record->created_at?->format('d/m/Y h:i A'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([OperationCashPayment::STATUS_REGISTERED => 'Registrado', OperationCashPayment::STATUS_REVERSED => 'Reversado']),
                SelectFilter::make('payment_currency')
                    ->label('Moneda')
                    ->options(['USD' => 'US$', 'VES' => 'Bolívares']),
                Filter::make('payment_date')
                    ->label('Fecha del pago')
                    ->schema([
                        DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', Carbon::parse($date)))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', Carbon::parse($date)))),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                Action::make('receipt')
                    ->label('Comprobante')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->url(fn (OperationCashPayment $record): ?string => self::receiptUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn (OperationCashPayment $record): bool => self::receiptUrl($record) !== null),
                Action::make('reverse')
                    ->label('Reversar pago')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (OperationCashPayment $record): bool => $record->isActive() && self::canReverse())
                    ->modalHeading('Reversar el pago de contado')
                    ->modalDescription(fn (OperationCashPayment $record): string => CashPaymentService::money((float) $record->payment_amount, (string) $record->payment_currency)
                        .' · ref. '.$record->payment_reference.' · '.$record->supplier_name
                        .'. El pago queda REVERSADO (no se borra) y la cuenta por pagar vuelve a como estaba antes del pago.')
                    ->modalContent(fn (OperationCashPayment $record): ?HtmlString => ($blocker = CashPaymentService::reversalBlocker($record)) !== null
                        ? new HtmlString('<p class="rounded-xl bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:bg-danger-500/10 dark:text-danger-300">'.e($blocker).'</p>')
                        : null)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Razón de la reversión')
                            ->helperText('Qué salió mal. Queda en el pago y en la auditoría.')
                            ->required()
                            ->minLength(CashPaymentService::MIN_REASON_LENGTH)
                            ->maxLength(2000)
                            ->rows(3),
                    ])
                    ->modalSubmitActionLabel('Reversar el pago')
                    ->action(function (OperationCashPayment $record, array $data, Action $action): void {
                        $user = Auth::user();

                        if (! $user instanceof User || ! self::canReverse()) {
                            Notification::make()->title('Sin permiso')->body('No tiene permiso para reversar pagos de contado.')->danger()->send();

                            return;
                        }

                        try {
                            CashPaymentService::reverse($record, (string) ($data['reason'] ?? ''), $user);
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title('No se reversó el pago')->body($exception->getMessage())->warning()->persistent()->send();
                            $action->halt();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title('No se reversó el pago')->body('Ocurrió un error y no se cambió nada. Intente nuevamente.')->danger()->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()
                            ->title('Pago reversado')
                            ->body('La cuenta por pagar volvió a «Pendiente por pagar». Si el pago se hizo de otra forma, regístrelo desde Cuentas por pagar.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function canReverse(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REVERSE_CASH_PAYMENTS);
    }

    private static function receiptUrl(OperationCashPayment $record): ?string
    {
        try {
            return filled($record->receipt_path) ? Storage::disk(AccountsPayablePaymentReceiptRegistrar::DISK)->url((string) $record->receipt_path) : null;
        } catch (Throwable) {
            return null;
        }
    }
}

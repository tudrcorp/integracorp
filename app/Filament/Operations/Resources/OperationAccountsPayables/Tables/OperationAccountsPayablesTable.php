<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Tables;

use App\Enums\StatusCuentaPorPagar;
use App\Models\BusinessUnit;
use App\Models\OperationAccountsPayable;
use App\Support\Operations\AccountsPayableInvoicePreview;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OperationAccountsPayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Cuentas por pagar')
            ->description('Facturas de proveedores, su estatus de pago y los montos cancelados en US$ y Bs.')
            ->defaultSort('invoice_registration_date', 'desc')
            ->persistFiltersInSession()
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['businessUnit:id,definition,code', 'operationServiceOrder:id,order_number']))
            ->columns([
                TextColumn::make('invoice_date')
                    ->label('Fecha factura')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days'),
                TextColumn::make('invoice_registration_date')
                    ->label('Fecha de registro')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->description(fn (OperationAccountsPayable $record): string => $record->supplier_rif),
                TextColumn::make('supplier_rif')
                    ->label('RIF')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('businessUnit.definition')
                    ->label('Unidad de negocio')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('operationServiceOrder.order_number')
                    ->label('Orden de servicio')
                    ->placeholder('Carga manual')
                    ->badge()
                    ->color(fn (OperationAccountsPayable $record): string => $record->operation_service_order_id !== null ? 'info' : 'gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('invoice_number')
                    ->label('N.º factura')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->copyMessage('Número de factura copiado'),
                TextColumn::make('invoice_control_number')
                    ->label('N.º control')
                    ->searchable()
                    ->placeholder('—')
                    ->badge()
                    ->color('warning')
                    ->toggleable(),
                TextColumn::make('invoice_amount')
                    ->label('Monto factura')
                    ->state(fn (OperationAccountsPayable $record): string => self::money($record->invoice_amount, $record->invoice_currency))
                    ->alignEnd()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('payment_status')
                    ->label('Estatus de pago')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => StatusCuentaPorPagar::labelFromMixed($state))
                    ->color(fn (mixed $state): string => StatusCuentaPorPagar::filamentColorFromMixed($state))
                    ->icon(fn (mixed $state): ?string => StatusCuentaPorPagar::fromStored($state)?->filamentIcon())
                    ->sortable(),
                TextColumn::make('payment_reference')
                    ->label('Referencia de pago')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->toggleable(),
                TextColumn::make('payment_date')
                    ->label('Fecha del pago')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('national_bank')
                    ->label('Banco nacional')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('international_bank')
                    ->label('Banco internacional')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_amount_usd')
                    ->label('Pago US$')
                    ->state(fn (OperationAccountsPayable $record): string => $record->payment_amount_usd !== null
                        ? self::money($record->payment_amount_usd, 'USD')
                        : '—')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total US$')->numeric(decimalPlaces: 2)),
                TextColumn::make('payment_amount_ves')
                    ->label('Pago Bs.')
                    ->state(fn (OperationAccountsPayable $record): string => $record->payment_amount_ves !== null
                        ? self::money($record->payment_amount_ves, 'VES')
                        : '—')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Bs.')->numeric(decimalPlaces: 2)),
                TextColumn::make('invoice_file_path')
                    ->label('Documento')
                    ->state(fn (OperationAccountsPayable $record): string => $record->hasInvoiceDocument() ? 'Adjunto' : 'Sin adjuntar')
                    ->badge()
                    ->icon(fn (OperationAccountsPayable $record): string => $record->hasInvoiceDocument() ? 'heroicon-m-paper-clip' : 'heroicon-m-exclamation-triangle')
                    ->color(fn (OperationAccountsPayable $record): string => $record->hasInvoiceDocument() ? 'success' : 'gray')
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Estatus de pago')
                    ->options(StatusCuentaPorPagar::options())
                    ->multiple(),
                SelectFilter::make('business_unit_id')
                    ->label('Unidad de negocio')
                    ->options(fn (): array => BusinessUnit::query()
                        ->orderBy('definition')
                        ->pluck('definition', 'id')
                        ->all())
                    ->searchable(),
                Filter::make('invoice_period')
                    ->label('Período de facturación')
                    ->form([
                        DatePicker::make('from')
                            ->label('Facturas desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Facturas hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('invoice_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('invoice_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'Desde '.date('d/m/Y', strtotime((string) $data['from']));
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = 'Hasta '.date('d/m/Y', strtotime((string) $data['until']));
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                AccountsPayableInvoicePreview::action(),
                ViewAction::make()->label('Ver'),
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar facturas seleccionadas')
                        ->modalDescription('Se eliminarán las facturas marcadas y su historial de pago. Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Eliminar')
                        ->modalCancelActionLabel('Cancelar'),
                ]),
            ])
            ->emptyStateHeading('Todavía no hay facturas registradas')
            ->emptyStateDescription('Las cuentas por pagar se generan al cargar la factura del proveedor en su orden de servicio.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    private static function money(mixed $amount, ?string $currency): string
    {
        $symbol = $currency === 'VES' ? 'Bs. ' : 'US$ ';

        return $symbol.number_format((float) $amount, 2, ',', '.');
    }
}

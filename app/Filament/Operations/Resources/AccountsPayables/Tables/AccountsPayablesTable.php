<?php

namespace App\Filament\Operations\Resources\AccountsPayables\Tables;

use App\Models\OperationQuoteGenerator;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\AccountsPayablePresenter;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsPayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Cuentas por pagar')
            ->description('Cotizaciones generadas para servicios coordinados, con montos en US$ y Bs. según tasa BCV.')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->whereNotNull('operation_coordination_service_id')
                    ->with([
                        'telemedicinePatient:id,full_name',
                        'telemedicineCase:id,code,patient_name',
                        'supplier:id,name,razon_social',
                        'operationServiceOrder:id,order_number,supplier_id,supplier_external',
                        'operationServiceOrder.supplier:id,name,razon_social',
                        'operationCoordinationService:id,patient,telemedicine_case_id',
                        'operationCoordinationService.telemedicineCase:id,code',
                    ])
                    ->whereHas(
                        'operationCoordinationService',
                        fn (Builder $coordinationQuery): Builder => OperationsSupplierScope::applyCoordinationListScope($coordinationQuery)
                    );
            })
            ->columns([
                TextColumn::make('patient')
                    ->label('Paciente')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::patientName($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $innerQuery) use ($search): void {
                            $innerQuery
                                ->whereHas('telemedicinePatient', fn (Builder $patientQuery): Builder => $patientQuery->where('full_name', 'like', "%{$search}%"))
                                ->orWhereHas('operationCoordinationService', fn (Builder $coordinationQuery): Builder => $coordinationQuery->where('patient', 'like', "%{$search}%"));
                        });
                    })
                    ->badge()
                    ->color('gray')
                    ->weight(FontWeight::Medium),
                TextColumn::make('telemedicineCase.code')
                    ->label('Número de caso')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::caseCode($record))
                    ->badge()
                    ->color('primary')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('telemedicineCase', fn (Builder $caseQuery): Builder => $caseQuery->where('code', 'like', "%{$search}%"));
                    }),
                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days'),
                TextColumn::make('quote_number')
                    ->label('N.º cotización')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::quoteNumber($record))
                    ->badge()
                    ->color('warning')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $normalized = mb_strtoupper(trim($search));

                        if (preg_match('/\d+/', $normalized, $matches) === 1) {
                            return $query->whereKey((int) $matches[0]);
                        }

                        return $query;
                    }),
                TextColumn::make('operationServiceOrder.order_number')
                    ->label('N.º orden de servicio')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::serviceOrderNumber($record) ?? '—')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::serviceOrderNumber($record) !== null ? 'info' : 'gray')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('operationServiceOrder', fn (Builder $orderQuery): Builder => $orderQuery->where('order_number', 'like', "%{$search}%"));
                    }),

                TextColumn::make('quote_amount_usd')
                    ->label('Monto cotización (US$)')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::formatUsd(
                        AccountsPayablePresenter::quoteAmountUsd($record)
                    ))
                    ->alignEnd()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total', $direction);
                    }),
                TextColumn::make('quote_amount_ves')
                    ->label('Monto cotización (Bs.)')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::formatVes(
                        AccountsPayablePresenter::quoteAmountVes($record)
                    ))
                    ->description(fn (OperationQuoteGenerator $record): ?string => ($rate = AccountsPayablePresenter::bcvRateForQuote($record)) !== null
                        ? 'Tasa BCV: '.number_format($rate, 2, '.', ',')
                        : null)
                    ->alignEnd(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor (cotización)')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::quoteSupplierLabel($record))
                    ->badge()
                    ->color('success')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                            $supplierQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('razon_social', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('order_supplier')
                    ->label('Proveedor (orden)')
                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::orderSupplierLabel($record) ?? '—')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::orderSupplierLabel($record) !== null ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

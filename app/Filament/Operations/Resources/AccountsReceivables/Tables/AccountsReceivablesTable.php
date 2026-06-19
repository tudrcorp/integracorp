<?php

namespace App\Filament\Operations\Resources\AccountsReceivables\Tables;

use App\Models\OperationAccountsReceivable;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\AccountsReceivablePresenter;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsReceivablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Cuentas por cobrar')
            ->description('Servicios reasignados a TDG por proveedores. Cotización y orden se completan cuando TDG gestiona el caso.')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->with([
                        'telemedicinePatient:id,full_name',
                        'telemedicineCase:id,code,patient_name',
                        'reassignmentSupplier:id,name,razon_social',
                        'reassignedByUser:id,name',
                        'operationCoordinationService:id,patient,telemedicine_case_id',
                        'operationCoordinationService.telemedicineCase:id,code',
                        'operationQuoteGenerator:id,total,costo_bolivares',
                        'operationServiceOrder:id,order_number',
                    ])
                    ->whereHas(
                        'operationCoordinationService',
                        fn (Builder $coordinationQuery): Builder => OperationsSupplierScope::applyCoordinationListScope($coordinationQuery)
                    );
            })
            ->columns([
                TextColumn::make('receivable_number')
                    ->label('N.º cuenta por cobrar')
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::receivableNumber($record))
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('patient')
                    ->label('Paciente')
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::patientName($record))
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
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::caseCode($record))
                    ->badge()
                    ->color('info')
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
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::quoteNumber($record) ?? '—')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::quoteNumber($record) !== null ? 'warning' : 'gray'),
                TextColumn::make('service_order_number')
                    ->label('N.º orden de servicio')
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::serviceOrderNumber($record) ?? '—')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::serviceOrderNumber($record) !== null ? 'info' : 'gray'),
                TextColumn::make('quote_amount_usd')
                    ->label('Monto cotización (US$)')
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::formatUsd(
                        AccountsReceivablePresenter::quoteAmountUsd($record)
                    ))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('quote_amount_ves')
                    ->label('Monto cotización (Bs.)')
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::formatVes(
                        AccountsReceivablePresenter::quoteAmountVes($record)
                    ))
                    ->description(fn (OperationAccountsReceivable $record): ?string => ($rate = AccountsReceivablePresenter::bcvRate($record)) !== null
                        ? 'Tasa BCV: '.number_format($rate, 2, '.', ',')
                        : null)
                    ->alignEnd(),
                TextColumn::make('reassignment_supplier_name')
                    ->label('Proveedor reasignación')
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::reassignmentSupplierName($record))
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('reassigned_by_analyst_name')
                    ->label('Analista')
                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::reassignedAnalystName($record))
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->formatStateUsing(fn (?string $state): string => AccountsReceivablePresenter::statusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => AccountsReceivablePresenter::statusColor($state)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}

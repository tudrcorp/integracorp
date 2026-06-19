<?php

namespace App\Filament\Operations\Resources\AccountsReceivables\Schemas;

use App\Models\OperationAccountsReceivable;
use App\Support\Operations\AccountsReceivablePresenter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AccountsReceivableInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('accountsReceivableInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen')
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Section::make('Datos generales')
                                    ->description('Información del paciente, caso y documentos vinculados')
                                    ->icon(Heroicon::UserGroup)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Identificación')
                                            ->schema([
                                                TextEntry::make('receivable_number')
                                                    ->label('N.º cuenta por cobrar')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::receivableNumber($record)),
                                                TextEntry::make('patient')
                                                    ->label('Paciente')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::patientName($record)),
                                                TextEntry::make('case_code')
                                                    ->label('Número de caso')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::caseCode($record)),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de creación')
                                                    ->dateTime('d/m/Y H:i'),
                                                TextEntry::make('status')
                                                    ->label('Estatus')
                                                    ->formatStateUsing(fn (?string $state): string => AccountsReceivablePresenter::statusLabel($state))
                                                    ->badge()
                                                    ->color(fn (?string $state): string => AccountsReceivablePresenter::statusColor($state)),
                                            ])
                                            ->columns(2),
                                        Fieldset::make('Documentos')
                                            ->schema([
                                                TextEntry::make('quote_number')
                                                    ->label('Número de cotización')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::quoteNumber($record) ?? '—'),
                                                TextEntry::make('service_order_number')
                                                    ->label('Número de orden de servicio')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::serviceOrderNumber($record) ?? '—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Montos')
                            ->icon(Heroicon::Banknotes)
                            ->schema([
                                Section::make('Montos de la cotización')
                                    ->description('Se completan automáticamente cuando TDG genera la cotización del servicio')
                                    ->icon(Heroicon::CurrencyDollar)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Importes')
                                            ->schema([
                                                TextEntry::make('quote_amount_usd')
                                                    ->label('Monto cotización (US$)')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::formatUsd(
                                                        AccountsReceivablePresenter::quoteAmountUsd($record)
                                                    )),
                                                TextEntry::make('quote_amount_ves')
                                                    ->label('Monto cotización (Bs.)')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::formatVes(
                                                        AccountsReceivablePresenter::quoteAmountVes($record)
                                                    )),
                                                TextEntry::make('bcv_rate')
                                                    ->label('Tasa BCV aplicada')
                                                    ->state(fn (OperationAccountsReceivable $record): string => ($rate = AccountsReceivablePresenter::bcvRate($record)) !== null
                                                        ? number_format($rate, 2, '.', ',')
                                                        : '—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Reasignación')
                            ->icon(Heroicon::ArrowsRightLeft)
                            ->schema([
                                Section::make('Origen de la reasignación')
                                    ->description('Proveedor y analista que transfirieron la gestión a TDG')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Responsables')
                                            ->schema([
                                                TextEntry::make('reassignment_supplier_name')
                                                    ->label('Proveedor que reasignó')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::reassignmentSupplierName($record)),
                                                TextEntry::make('reassigned_by_analyst_name')
                                                    ->label('Analista que ejecutó la acción')
                                                    ->state(fn (OperationAccountsReceivable $record): string => AccountsReceivablePresenter::reassignedAnalystName($record)),
                                                TextEntry::make('reassignment_reason')
                                                    ->label('Motivo de reasignación')
                                                    ->columnSpanFull()
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

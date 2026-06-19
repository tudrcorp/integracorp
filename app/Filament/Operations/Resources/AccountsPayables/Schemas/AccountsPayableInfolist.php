<?php

namespace App\Filament\Operations\Resources\AccountsPayables\Schemas;

use App\Models\OperationQuoteGenerator;
use App\Support\Operations\AccountsPayablePresenter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AccountsPayableInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('accountsPayableInfolistTabs')
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
                                                TextEntry::make('patient')
                                                    ->label('Paciente')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::patientName($record)),
                                                TextEntry::make('case_code')
                                                    ->label('Número de caso')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::caseCode($record)),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de creación')
                                                    ->dateTime('d/m/Y H:i'),
                                                TextEntry::make('status')
                                                    ->label('Estatus de cotización')
                                                    ->badge(),
                                            ])
                                            ->columns(2),
                                        Fieldset::make('Documentos')
                                            ->schema([
                                                TextEntry::make('quote_number')
                                                    ->label('Número de cotización')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::quoteNumber($record)),
                                                TextEntry::make('service_order_number')
                                                    ->label('Número de orden de servicio')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::serviceOrderNumber($record) ?? '—'),
                                                TextEntry::make('type_service')
                                                    ->label('Tipo de servicio')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Montos y proveedores')
                            ->icon(Heroicon::Banknotes)
                            ->schema([
                                Section::make('Montos de la cotización')
                                    ->description('Valores en dólares y bolívares según la tasa BCV aplicada')
                                    ->icon(Heroicon::CurrencyDollar)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Importes')
                                            ->schema([
                                                TextEntry::make('quote_amount_usd')
                                                    ->label('Monto cotización (US$)')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::formatUsd(
                                                        AccountsPayablePresenter::quoteAmountUsd($record)
                                                    )),
                                                TextEntry::make('quote_amount_ves')
                                                    ->label('Monto cotización (Bs.)')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::formatVes(
                                                        AccountsPayablePresenter::quoteAmountVes($record)
                                                    )),
                                                TextEntry::make('bcv_rate')
                                                    ->label('Tasa BCV aplicada')
                                                    ->state(fn (OperationQuoteGenerator $record): string => ($rate = AccountsPayablePresenter::bcvRateForQuote($record)) !== null
                                                        ? number_format($rate, 2, '.', ',')
                                                        : '—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Proveedores')
                                    ->description('Proveedor asociado a la cotización y a la orden de servicio')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Asignación')
                                            ->schema([
                                                TextEntry::make('quote_supplier')
                                                    ->label('Proveedor (cotización)')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::quoteSupplierLabel($record)),
                                                TextEntry::make('order_supplier')
                                                    ->label('Proveedor (orden de servicio)')
                                                    ->state(fn (OperationQuoteGenerator $record): string => AccountsPayablePresenter::orderSupplierLabel($record) ?? '—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

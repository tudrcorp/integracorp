<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Sales\Schemas;

use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\Sale;
use App\Support\FilamentDateDisplay;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class SaleInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-0 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] ring-0 dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:ring-0 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner ring-0 outline-none dark:border-white/10 dark:bg-white/5 dark:shadow-none dark:ring-0 sm:p-5';

    private const IOS_INSET_GROUP_CLASS = 'rounded-xl border border-slate-200/60 bg-slate-50/50 p-3 ring-0 dark:border-white/10 dark:bg-white/[0.04] dark:ring-0 sm:p-4';

    /** Hero exterior: sin ring en dark (evita borde cyan/verde de Filament). */
    private const HERO_SECTION_BASE = 'rounded-[1.75rem] border bg-gradient-to-br ring-0 outline-none backdrop-blur-sm dark:border-white/10 dark:from-slate-900/95 dark:via-gray-900/95 dark:to-slate-950 dark:ring-0 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const SALE_HERO_SECTION = self::HERO_SECTION_BASE.' border-sky-200/70 from-sky-50/95 via-white to-slate-50/90 shadow-[0_24px_60px_-24px_rgba(14,165,233,0.28)]';

    private const RECEIPT_HERO_SECTION = self::HERO_SECTION_BASE.' border-emerald-200/70 from-emerald-50/95 via-white to-slate-50/90 shadow-[0_24px_60px_-24px_rgba(16,185,129,0.28)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('saleInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Venta')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                self::saleHeroSection(),
                                self::saleDetailsSection(),
                            ]),
                        Tab::make('Afiliación')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                self::affiliationSection(),
                                self::commercialSection(),
                            ]),
                        Tab::make('Recibo de pago')
                            ->icon('heroicon-o-document-currency-dollar')
                            ->schema([
                                self::paidReceiptEmptyNotice(),
                                self::paidReceiptHeroSection(),
                                self::paidReceiptPlanSection(),
                                self::paidReceiptAmountsSection(),
                                self::paidReceiptPaymentChannelsSection(),
                                self::paidReceiptDatesSection(),
                                self::paidReceiptDocumentsSection(),
                                self::paidReceiptAuditSection(),
                            ]),
                    ]),
            ]);
    }

    private static function saleHeroSection(): Section
    {
        return Section::make(fn (Sale $record): string => 'Venta · Recibo #'.($record->invoice_number ?? '—'))
            ->description(fn (Sale $record): string => 'Registro en sales · '.($record->created_at?->format('d/m/Y H:i') ?? 'Sin fecha'))
            ->icon('heroicon-o-currency-dollar')
            ->extraAttributes(['class' => self::SALE_HERO_SECTION])
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->size('lg')
                                    ->color(fn (?string $state): string => match ($state) {
                                        'AFILIACION INDIVIDUAL' => 'primary',
                                        'AFILIACION CORPORATIVA' => 'success',
                                        default => 'gray',
                                    }),
                                TextEntry::make('total_amount')
                                    ->label('Monto total')
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsd($state))
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('success'),
                                TextEntry::make('payment_frequency')
                                    ->label('Frecuencia')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('status_payment_commission')
                                    ->label('Comisión')
                                    ->badge()
                                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                                        'COMISION PAGADA' => 'success',
                                        'POR PAGAR' => 'warning',
                                        default => 'gray',
                                    }),
                            ]),
                        TextEntry::make('payment_method')
                            ->label('Método de pago')
                            ->icon('heroicon-m-credit-card')
                            ->badge()
                            ->color('gray'),
                    ]),
            ]);
    }

    private static function saleDetailsSection(): Section
    {
        return Section::make('Detalle completo')
            ->description('Información del registro en la tabla sales.')
            ->icon('heroicon-o-clipboard-document-list')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('invoice_number')
                                    ->label('Nro. recibo de pago')
                                    ->icon('heroicon-m-document-text')
                                    ->copyable()
                                    ->copyMessage('Nro. de recibo copiado')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro')
                                    ->icon('heroicon-m-calendar-days')
                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                    ->placeholder('—'),
                                TextEntry::make('date_activation')
                                    ->label('Fecha de activación')
                                    ->icon('heroicon-m-calendar')
                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                    ->placeholder('—'),
                                TextEntry::make('total_amount_ves')
                                    ->label('Monto total (VES)')
                                    ->formatStateUsing(fn (mixed $state): string => self::formatVes($state))
                                    ->placeholder('—'),
                                TextEntry::make('pay_amount_usd')
                                    ->label('Pago registrado (US$)')
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsd($state))
                                    ->placeholder('—'),
                                TextEntry::make('pay_amount_ves')
                                    ->label('Pago registrado (VES)')
                                    ->formatStateUsing(fn (mixed $state): string => self::formatVes($state))
                                    ->placeholder('—'),
                                TextEntry::make('bank')
                                    ->label('Banco')
                                    ->placeholder('—'),
                                TextEntry::make('bank_usd')
                                    ->label('Banco US$')
                                    ->placeholder('—'),
                                TextEntry::make('bank_ves')
                                    ->label('Banco VES')
                                    ->placeholder('—'),
                                TextEntry::make('reference_payment')
                                    ->label('Referencia de pago')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('payment_date')
                                    ->label('Fecha de pago')
                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                    ->placeholder('—'),
                                TextEntry::make('date_payment_voucher')
                                    ->label('Fecha comprobante')
                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                    ->placeholder('—'),
                                TextEntry::make('invoice_generated')
                                    ->label('Factura generada')
                                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? 'FACT-'.(string) $state : 'No generada')
                                    ->placeholder('—'),
                                TextEntry::make('is_payment_link')
                                    ->label('Enlace de pago')
                                    ->formatStateUsing(fn (mixed $state): string => $state ? 'Sí' : 'No')
                                    ->badge()
                                    ->color(fn (mixed $state): string => $state ? 'success' : 'gray'),
                                TextEntry::make('type_roll')
                                    ->label('Tipo de rol')
                                    ->placeholder('—'),
                                TextEntry::make('service')
                                    ->label('Servicio')
                                    ->placeholder('—'),
                                TextEntry::make('persons')
                                    ->label('Personas')
                                    ->placeholder('—'),
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—'),
                                TextEntry::make('observations')
                                    ->label('Observaciones')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function affiliationSection(): Section
    {
        return Section::make('Afiliación y titular')
            ->description('Datos del afiliado asociados a la venta.')
            ->icon('heroicon-o-user-group')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('affiliation_code')
                                    ->label('Código de afiliación')
                                    ->icon('heroicon-m-qr-code')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('affiliation_id')
                                    ->label('ID afiliación')
                                    ->placeholder('—'),
                                TextEntry::make('affiliate_full_name')
                                    ->label('Nombre del afiliado')
                                    ->weight('medium')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('affiliate_ci_rif')
                                    ->label('CI / RIF')
                                    ->placeholder('—'),
                                TextEntry::make('affiliate_contact')
                                    ->label('Contacto')
                                    ->placeholder('—'),
                                TextEntry::make('affiliate_phone')
                                    ->label('Teléfono')
                                    ->icon('heroicon-m-phone')
                                    ->placeholder('—'),
                                TextEntry::make('affiliate_email')
                                    ->label('Correo')
                                    ->icon('heroicon-m-envelope')
                                    ->placeholder('—'),
                                TextEntry::make('plan.description')
                                    ->label('Plan (venta)')
                                    ->placeholder('—'),
                                TextEntry::make('coverage.price')
                                    ->label('Cobertura (US$)')
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsd($state))
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    private static function commercialSection(): Section
    {
        return Section::make('Estructura comercial')
            ->description('Agencia y agente que originaron la venta.')
            ->icon('heroicon-o-building-office-2')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('code_agency')
                                    ->label('Código agencia')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('agency.name_corporative')
                                    ->label('Agencia')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('owner_code')
                                    ->label('Código propietario')
                                    ->placeholder('—'),
                                TextEntry::make('agencyMasterName.name_corporative')
                                    ->label('Agencia master')
                                    ->placeholder('—'),
                                TextEntry::make('agent.name')
                                    ->label('Agente')
                                    ->icon('heroicon-m-user')
                                    ->placeholder('—'),
                                TextEntry::make('agent_id')
                                    ->label('ID agente')
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    private static function paidReceiptEmptyNotice(): Section
    {
        return Section::make('Sin recibo vinculado')
            ->description('No existe registro en paid_memberships / paid_membership_corporates para esta venta.')
            ->icon('heroicon-o-exclamation-triangle')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() === null)
            ->schema([
                Text::make('paid_receipt_missing')
                    ->content('Regenere el recibo o verifique que el número de factura coincida con el pago registrado.'),
            ]);
    }

    private static function paidReceiptHeroSection(): Section
    {
        return Section::make(fn (Sale $record): string => 'Recibo #'.($record->resolvePaidReceipt()?->invoice_number ?? '—'))
            ->description(fn (Sale $record): string => 'Origen: '.$record->paidReceiptTableName().' · ID interno '.($record->resolvePaidReceipt()?->getKey() ?? '—'))
            ->icon('heroicon-o-document-check')
            ->extraAttributes(['class' => self::RECEIPT_HERO_SECTION])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() !== null)
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('paid_receipt_status')
                                    ->label('Estatus')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->status)
                                    ->badge()
                                    ->size('lg')
                                    ->color(fn (mixed $state): string => strtoupper((string) $state) === 'APROBADO' ? 'success' : 'warning'),
                                TextEntry::make('paid_receipt_plan')
                                    ->label('Plan')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->plan?->description)
                                    ->weight('semibold')
                                    ->placeholder('—'),
                                TextEntry::make('paid_receipt_total')
                                    ->label('Monto total')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->total_amount)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsd($state))
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('success'),
                                TextEntry::make('paid_receipt_frequency')
                                    ->label('Frecuencia')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->payment_frequency)
                                    ->badge()
                                    ->color('info'),
                            ]),
                        TextEntry::make('paid_receipt_method')
                            ->label('Método de pago principal')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->payment_method)
                            ->icon('heroicon-m-credit-card')
                            ->badge()
                            ->color('gray'),
                    ]),
            ]);
    }

    private static function paidReceiptPlanSection(): Section
    {
        return Section::make('Plan y afiliación')
            ->icon('heroicon-o-clipboard-document-list')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() !== null)
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('paid_receipt_invoice')
                                    ->label('Nro. recibo')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->invoice_number)
                                    ->icon('heroicon-m-document-text')
                                    ->copyable()
                                    ->copyMessage('Nro. copiado'),
                                TextEntry::make('paid_receipt_coverage')
                                    ->label('Cobertura')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->coverage?->price)
                                    ->formatStateUsing(fn (mixed $state): string => self::formatUsd($state)),
                                TextEntry::make('paid_receipt_affiliation_id')
                                    ->label('ID afiliación')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt() instanceof PaidMembership
                                        ? $record->resolvePaidReceipt()->affiliation_id
                                        : ($record->resolvePaidReceipt() instanceof PaidMembershipCorporate
                                            ? $record->resolvePaidReceipt()->affiliation_corporate_id
                                            : null)),
                                TextEntry::make('paid_receipt_type_roll')
                                    ->label('Tipo de rol')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->type_roll)
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('paid_receipt_name_ti')
                                    ->label('Titular (USD)')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt() instanceof PaidMembership
                                        ? $record->resolvePaidReceipt()->name_ti_usd
                                        : null)
                                    ->visible(fn (Sale $record): bool => $record->type === 'AFILIACION INDIVIDUAL'
                                        && self::receiptHasValue($record, 'name_ti_usd')),
                                TextEntry::make('paid_receipt_code_agency')
                                    ->label('Código agencia')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->code_agency)
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('paid_receipt_agent')
                                    ->label('Agente')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->agent?->name)
                                    ->icon('heroicon-m-user')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function paidReceiptAmountsSection(): Section
    {
        return Section::make('Montos registrados')
            ->description('Importes consolidados del pago.')
            ->icon('heroicon-o-banknotes')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() !== null)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 3])
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        TextEntry::make('paid_receipt_pay_usd')
                            ->label('Pago en US$')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->pay_amount_usd)
                            ->formatStateUsing(fn (mixed $state): string => self::formatUsd($state))
                            ->icon('heroicon-m-currency-dollar')
                            ->weight('semibold'),
                        TextEntry::make('paid_receipt_pay_ves')
                            ->label('Pago en VES')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->pay_amount_ves)
                            ->formatStateUsing(fn (mixed $state): string => self::formatVes($state))
                            ->icon('heroicon-m-banknotes')
                            ->weight('semibold')
                            ->color('warning'),
                        TextEntry::make('paid_receipt_tasa')
                            ->label('Tasa BCV')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->tasa_bcv)
                            ->suffix(' VES')
                            ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'tasa_bcv')),
                    ]),
            ]);
    }

    private static function paidReceiptPaymentChannelsSection(): Section
    {
        return Section::make('Canales de pago')
            ->description('Desglose por moneda: solo se muestran datos con valor.')
            ->icon('heroicon-o-arrows-right-left')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() !== null)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes(['class' => self::IOS_INSET_GROUP_CLASS])
                            ->schema([
                                Text::make('paid_usd_title')
                                    ->content('Dólares (USD)')
                                    ->weight('semibold'),
                                TextEntry::make('paid_receipt_method_usd')
                                    ->label('Método')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->payment_method_usd)
                                    ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'payment_method_usd')),
                                TextEntry::make('paid_receipt_bank_usd')
                                    ->label('Banco')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->bank_usd)
                                    ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'bank_usd')),
                                TextEntry::make('paid_receipt_ref_usd')
                                    ->label('Referencia')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->reference_payment_usd)
                                    ->copyable()
                                    ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'reference_payment_usd')),
                            ]),
                        Grid::make(1)
                            ->extraAttributes(['class' => self::IOS_INSET_GROUP_CLASS])
                            ->schema([
                                Text::make('paid_ves_title')
                                    ->content('Bolívares (VES)')
                                    ->weight('semibold'),
                                TextEntry::make('paid_receipt_method_ves')
                                    ->label('Método')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->payment_method_ves)
                                    ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'payment_method_ves')),
                                TextEntry::make('paid_receipt_bank_ves')
                                    ->label('Banco')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->bank_ves)
                                    ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'bank_ves')),
                                TextEntry::make('paid_receipt_ref_ves')
                                    ->label('Referencia')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->reference_payment_ves)
                                    ->copyable()
                                    ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'reference_payment_ves')),
                            ]),
                    ]),
            ]);
    }

    private static function paidReceiptDatesSection(): Section
    {
        return Section::make('Vigencia y fechas')
            ->icon('heroicon-o-calendar-days')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() !== null)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        TextEntry::make('paid_receipt_payment_date')
                            ->label('Pagado desde')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->payment_date)
                            ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                            ->icon('heroicon-m-calendar'),
                        TextEntry::make('paid_receipt_prox_date')
                            ->label('Pagado hasta')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->prox_payment_date)
                            ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                            ->icon('heroicon-m-calendar'),
                        TextEntry::make('paid_receipt_renewal')
                            ->label('Renovación')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->renewal_date)
                            ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('paid_receipt_voucher_date')
                            ->label('Fecha comprobante')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->date_payment_voucher)
                            ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state)),
                    ]),
            ]);
    }

    private static function paidReceiptDocumentsSection(): Section
    {
        return Section::make('Comprobantes')
            ->icon('heroicon-o-paper-clip')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() !== null)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        IconEntry::make('paid_receipt_has_doc_usd')
                            ->label('Comprobante US$')
                            ->state(fn (Sale $record): bool => self::documentUrl($record->resolvePaidReceipt(), 'document_usd') !== null)
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                        TextEntry::make('paid_receipt_document_usd_link')
                            ->label('Archivo US$')
                            ->state(fn (Sale $record): string => self::documentLabel($record->resolvePaidReceipt(), 'document_usd'))
                            ->url(fn (Sale $record): ?string => self::documentUrl($record->resolvePaidReceipt(), 'document_usd'))
                            ->openUrlInNewTab()
                            ->color(fn (Sale $record): string => self::documentUrl($record->resolvePaidReceipt(), 'document_usd') ? 'primary' : 'gray'),
                        IconEntry::make('paid_receipt_has_doc_ves')
                            ->label('Comprobante VES')
                            ->state(fn (Sale $record): bool => self::documentUrl($record->resolvePaidReceipt(), 'document_ves') !== null)
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('gray'),
                        TextEntry::make('paid_receipt_document_ves_link')
                            ->label('Archivo VES')
                            ->state(fn (Sale $record): string => self::documentLabel($record->resolvePaidReceipt(), 'document_ves'))
                            ->url(fn (Sale $record): ?string => self::documentUrl($record->resolvePaidReceipt(), 'document_ves'))
                            ->openUrlInNewTab()
                            ->color(fn (Sale $record): string => self::documentUrl($record->resolvePaidReceipt(), 'document_ves') ? 'primary' : 'gray'),
                    ]),
            ]);
    }

    private static function paidReceiptAuditSection(): Section
    {
        return Section::make('Seguimiento')
            ->description('Registro y observaciones del pago.')
            ->icon('heroicon-o-clipboard-document-check')
            ->extraAttributes(['class' => self::SECTION_CARD])
            ->visible(fn (Sale $record): bool => $record->resolvePaidReceipt() !== null)
            ->schema([
                Grid::make(1)
                    ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('paid_receipt_created_by')
                                    ->label('Creado por')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->created_by)
                                    ->icon('heroicon-m-user-plus'),
                                TextEntry::make('paid_receipt_aproved_by')
                                    ->label('Aprobado por')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->aproved_by)
                                    ->icon('heroicon-m-check-badge')
                                    ->color('success'),
                                TextEntry::make('paid_receipt_created_at')
                                    ->label('Registrado el')
                                    ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->created_at)
                                    ->dateTime('d/m/Y H:i')
                                    ->icon('heroicon-m-clock'),
                            ]),
                        TextEntry::make('paid_receipt_observations')
                            ->label('Observaciones')
                            ->state(fn (Sale $record): mixed => $record->resolvePaidReceipt()?->observations_payment)
                            ->placeholder('Sin observaciones')
                            ->visible(fn (Sale $record): bool => self::receiptHasValue($record, 'observations_payment'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function receiptHasValue(Sale $record, string $attribute): bool
    {
        $receipt = $record->resolvePaidReceipt();

        if ($receipt === null) {
            return false;
        }

        $value = $receipt->{$attribute} ?? null;

        if (blank($value)) {
            return false;
        }

        return (string) $value !== 'N/A';
    }

    private static function formatUsd(mixed $state): string
    {
        if (blank($state) || $state === 'N/A') {
            return '—';
        }

        return number_format((float) $state, 2, ',', '.').' US$';
    }

    private static function formatVes(mixed $state): string
    {
        if (blank($state) || $state === 'N/A') {
            return '—';
        }

        return number_format((float) $state, 2, ',', '.').' VES';
    }

    private static function documentLabel(PaidMembership|PaidMembershipCorporate|null $receipt, string $attribute): string
    {
        if ($receipt === null) {
            return '—';
        }

        $value = $receipt->{$attribute} ?? null;

        if (blank($value) || $value === 'N/A') {
            return 'Sin comprobante';
        }

        return 'Ver comprobante';
    }

    private static function documentUrl(PaidMembership|PaidMembershipCorporate|null $receipt, string $attribute): ?string
    {
        if ($receipt === null) {
            return null;
        }

        $value = $receipt->{$attribute} ?? null;

        if (blank($value) || $value === 'N/A') {
            return null;
        }

        return asset('storage/'.$value);
    }
}

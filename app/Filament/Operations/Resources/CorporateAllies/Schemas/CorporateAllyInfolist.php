<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CorporateAllies\Schemas;

use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CorporateAllyInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_TABLE_WRAP_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/90 shadow-sm dark:border-white/10 dark:bg-gray-900/40 overflow-hidden';

    private static function agreementStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'AFILIADO', 'ACTIVO', 'VIGENTE' => 'success',
            'EN PROCESO', 'PENDIENTE', 'POR REVISION', 'POR REVISIÓN' => 'warning',
            'INACTIVO', 'SUSPENDIDO', 'RECHAZADO' => 'danger',
            default => 'gray',
        };
    }

    private static function systemStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'AFILIADO', 'ACTIVO' => 'success',
            'EN PROCESO' => 'warning',
            'INACTIVO', 'SUSPENDIDO' => 'danger',
            default => 'gray',
        };
    }

    private static function typeAgreementColor(?string $state): string
    {
        $upper = strtoupper((string) $state);

        return match (true) {
            str_contains($upper, 'PREFERENCIAL') => 'success',
            str_contains($upper, 'GENERAL') => 'info',
            filled($state) => 'gray',
            default => 'gray',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('corporateAllyInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos principales')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Aliado corporativo')
                                    ->description('Identificación, categoría y estatus del convenio.')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 5])
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('company_name')
                                                    ->label('Razón social')
                                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                                    ->weight('semibold')
                                                    ->size('lg')
                                                    ->color('gray')
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('rif')
                                                    ->label('RIF')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->badge()
                                                    ->color('info')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('supplier_category')
                                                    ->label('Categoría')
                                                    ->icon(Heroicon::OutlinedTag)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('type_agreement')
                                                    ->label('Tipo de convenio')
                                                    ->icon(Heroicon::OutlinedDocumentText)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::typeAgreementColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('status_agreement')
                                                    ->label('Estatus del convenio')
                                                    ->icon(Heroicon::OutlinedDocumentCheck)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::agreementStatusColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estatus en sistema')
                                                    ->icon(Heroicon::OutlinedSignal)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::systemStatusColor($state))
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Ubicación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Ubicación')
                                    ->description('País, estado, ciudad y dirección del aliado.')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('country.name')
                                                    ->label('País')
                                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->placeholder('—'),
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->icon(Heroicon::OutlinedMap)
                                                    ->placeholder('—'),
                                                TextEntry::make('address')
                                                    ->label('Dirección')
                                                    ->icon(Heroicon::OutlinedHomeModern)
                                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                                                    ->wrap()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Contacto')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Contacto')
                                    ->description('Teléfonos, correo y redes sociales.')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('phone')
                                                    ->label('Teléfono principal')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('people_contact')
                                                    ->label('Teléfono secundario')
                                                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('email')
                                                    ->label('Correo electrónico')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('social_networks')
                                                    ->label('Redes sociales')
                                                    ->icon(Heroicon::OutlinedShare)
                                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                    ->wrap()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Servicios y condiciones')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Section::make('Servicios y pagos')
                                    ->description('Servicios ofrecidos y condiciones de pago.')
                                    ->icon(Heroicon::OutlinedBriefcase)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('services')
                                                    ->label('Servicios')
                                                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('payment_term')
                                                    ->label('Plazo de pago')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('supplier_payment')
                                                    ->label('Forma de pago')
                                                    ->icon(Heroicon::OutlinedBanknotes)
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Datos bancarios')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Section::make('Banca en moneda local')
                                    ->description('Beneficiario, cuenta local y pago móvil.')
                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('local_beneficiary_name')
                                                    ->label('Beneficiario')
                                                    ->icon('heroicon-o-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_rif')
                                                    ->label('R.I.F.')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_account_number')
                                                    ->label('N° cuenta')
                                                    ->icon(Heroicon::OutlinedCreditCard)
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_account_type')
                                                    ->label('Tipo de cuenta')
                                                    ->icon('heroicon-o-credit-card')
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_phone_pm')
                                                    ->label('Pago móvil')
                                                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_account_number_mon_inter')
                                                    ->label('Cuenta moneda extranjera (local)')
                                                    ->icon(Heroicon::OutlinedCreditCard)
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_account_bank_mon_inter')
                                                    ->label('Banco (inter.)')
                                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                                    ->placeholder('—'),
                                                TextEntry::make('local_beneficiary_account_type_mon_inter')
                                                    ->label('Tipo (inter.)')
                                                    ->icon('heroicon-o-credit-card')
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Banca en moneda extranjera')
                                    ->description('Cuenta internacional, Zelle y datos SWIFT / ACH.')
                                    ->icon(Heroicon::OutlinedGlobeAlt)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('extra_beneficiary_name')
                                                    ->label('Beneficiario')
                                                    ->icon('heroicon-o-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_ci_rif')
                                                    ->label('CI / RIF')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_account_number')
                                                    ->label('N° cuenta')
                                                    ->icon(Heroicon::OutlinedCreditCard)
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_account_type')
                                                    ->label('Tipo de cuenta')
                                                    ->icon('heroicon-o-credit-card')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_route')
                                                    ->label('Routing')
                                                    ->icon('heroicon-o-arrow-path')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_zelle')
                                                    ->label('Zelle')
                                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_ach')
                                                    ->label('ACH')
                                                    ->icon('heroicon-o-arrow-right')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_swift')
                                                    ->label('SWIFT')
                                                    ->icon('heroicon-o-bolt')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_aba')
                                                    ->label('ABA')
                                                    ->icon('heroicon-o-hashtag')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_address')
                                                    ->label('Dirección')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 5])
                                                    ->wrap()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Bitácora')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Section::make('Bitácora de notas y observaciones')
                                    ->description('Historial de anotaciones operativas del aliado corporativo.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        RepeatableEntry::make('corporateAllyObservacions')
                                            ->label('Registros')
                                            ->placeholder('No posee notas u observaciones.')
                                            ->extraAttributes(['class' => self::IOS_TABLE_WRAP_CLASS])
                                            ->table([
                                                TableColumn::make('Notas y/o observación'),
                                                TableColumn::make('Responsable de la nota'),
                                                TableColumn::make('Fecha de la nota'),
                                            ])
                                            ->schema([
                                                TextEntry::make('observation')->wrap(),
                                                TextEntry::make('created_by'),
                                                TextEntry::make('created_at')
                                                    ->dateTime('d/m/Y H:i:s'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

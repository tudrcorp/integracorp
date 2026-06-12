<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Models\Agent;
use App\Models\Country;
use App\Models\ObservationCommercialStructure;
use App\Support\CountrySelectOptions;
use App\Support\Filament\CommercialStructure\AgentAddressClipboardFormat;
use App\Support\FilamentDateDisplay;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\HtmlString;

class AgentInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_INSET_GROUP_CLASS = 'rounded-xl border border-slate-200/60 bg-slate-50/50 p-3 dark:border-white/10 dark:bg-white/[0.04] sm:p-4';

    private const IOS_ADDRESS_VENEZUELA_CARD = 'rounded-[1.25rem] border border-emerald-200/75 bg-gradient-to-br from-emerald-50/95 via-white to-slate-50/85 p-4 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.92),0_10px_28px_-12px_rgba(16,185,129,0.18)] ring-1 ring-emerald-300/40 dark:border-emerald-500/30 dark:from-emerald-950/35 dark:via-gray-900/90 dark:to-slate-950/90 dark:ring-emerald-400/25 sm:p-5';

    private const IOS_ADDRESS_INTERNATIONAL_CARD = 'rounded-[1.25rem] border border-sky-200/75 bg-gradient-to-br from-sky-50/95 via-white to-slate-50/85 p-4 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.92),0_10px_28px_-12px_rgba(14,165,233,0.16)] ring-1 ring-sky-300/40 dark:border-sky-500/30 dark:from-sky-950/35 dark:via-gray-900/90 dark:to-slate-950/90 dark:ring-sky-400/25 sm:p-5';

    private static function agentStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA' => 'success',
            'PENDIENTE' => 'warning',
            'INACTIVO', 'INACTIVA' => 'danger',
            default => 'gray',
        };
    }

    private static function affiliationStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVA', 'ACTIVO' => 'success',
            'PENDIENTE' => 'warning',
            default => 'gray',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('agentInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Agente')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Section::make('Agente')
                                    ->description('Identificación principal, contacto y estado.')
                                    ->icon('heroicon-o-user-circle')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Nombre')
                                                    ->size('lg')
                                                    ->weight('semibold')
                                                    ->color('gray')
                                                    ->placeholder('Sin nombre'),
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                                    ->schema([
                                                        TextEntry::make('ci')
                                                            ->label('C.I.')
                                                            ->icon('heroicon-m-identification')
                                                            ->copyable()
                                                            ->copyMessage('C.I. copiada')
                                                            ->placeholder('—'),
                                                        TextEntry::make('rif')
                                                            ->label('R.I.F.')
                                                            ->icon('heroicon-m-identification')
                                                            ->copyable()
                                                            ->copyMessage('RIF copiado')
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->label('Correo')
                                                            ->icon('heroicon-m-envelope')
                                                            ->copyable()
                                                            ->copyMessage('Correo copiado')
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone')
                                                            ->label('Teléfono')
                                                            ->icon('heroicon-m-phone')
                                                            ->copyable()
                                                            ->copyMessage('Teléfono copiado')
                                                            ->placeholder('—'),
                                                        TextEntry::make('birth_date')
                                                            ->label('Fecha de nacimiento')
                                                            ->icon('heroicon-m-cake')
                                                            ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('sex')
                                                            ->label('Sexo')
                                                            ->placeholder('—'),
                                                        TextEntry::make('marital_status')
                                                            ->label('Estado civil')
                                                            ->placeholder('—'),
                                                        TextEntry::make('owner_code')
                                                            ->label('Código propietario')
                                                            ->icon('heroicon-m-qr-code')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->placeholder('—'),
                                                        TextEntry::make('code_agent')
                                                            ->label('Código agente')
                                                            ->icon('heroicon-m-identification')
                                                            ->placeholder('—'),
                                                        TextEntry::make('agency.name_corporative')
                                                            ->label('Agencia')
                                                            ->icon('heroicon-m-building-office-2')
                                                            ->weight('medium')
                                                            ->placeholder('—'),
                                                        TextEntry::make('status')
                                                            ->label('Estado')
                                                            ->icon('heroicon-m-signal')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::agentStatusColor($state))
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                        Grid::make(['default' => 1, 'xl' => 2])
                                            ->schema([
                                                Grid::make(1)
                                                    ->extraAttributes([
                                                        'class' => self::IOS_ADDRESS_VENEZUELA_CARD,
                                                    ])
                                                    ->schema([
                                                        Text::make('Dirección en Venezuela')
                                                            ->icon('heroicon-o-map-pin')
                                                            ->weight('semibold')
                                                            ->color('success'),
                                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                            ->extraAttributes([
                                                                'class' => self::IOS_INSET_GROUP_CLASS,
                                                            ])
                                                            ->schema([
                                                                TextEntry::make('country.name')
                                                                    ->label('País')
                                                                    ->icon('heroicon-m-globe-americas')
                                                                    ->badge()
                                                                    ->color('success')
                                                                    ->placeholder('—'),
                                                                TextEntry::make('state.definition')
                                                                    ->label('Estado')
                                                                    ->icon('heroicon-m-map')
                                                                    ->badge()
                                                                    ->color('gray')
                                                                    ->placeholder('—'),
                                                                TextEntry::make('city.definition')
                                                                    ->label('Ciudad')
                                                                    ->icon('heroicon-m-building-office')
                                                                    ->badge()
                                                                    ->color('gray')
                                                                    ->placeholder('—'),
                                                            ]),
                                                        TextEntry::make('address')
                                                            ->label('Dirección fiscal')
                                                            ->icon('heroicon-m-home')
                                                            ->iconColor('success')
                                                            ->weight('semibold')
                                                            ->size(TextSize::Medium)
                                                            ->wrap()
                                                            ->formatStateUsing(fn (?string $state): ?string => self::formatAddress($state))
                                                            ->helperText(fn (Agent $record): ?string => filled(self::venezuelaLocationSummary($record))
                                                                ? 'Ubicación: '.self::venezuelaLocationSummary($record)
                                                                : null)
                                                            ->placeholder('Sin dirección registrada en Venezuela'),
                                                        TextEntry::make('venezuela_address_copy')
                                                            ->hiddenLabel()
                                                            ->badge()
                                                            ->color('success')
                                                            ->icon('heroicon-o-clipboard-document')
                                                            ->state('Copiar dirección')
                                                            ->copyable()
                                                            ->copyableState(fn (Agent $record): string => AgentAddressClipboardFormat::venezuela($record))
                                                            ->copyMessage('Formato de correspondencia copiado')
                                                            ->visible(fn (Agent $record): bool => AgentAddressClipboardFormat::canCopyVenezuela($record)),
                                                    ]),
                                                Grid::make(1)
                                                    ->extraAttributes([
                                                        'class' => self::IOS_ADDRESS_INTERNATIONAL_CARD,
                                                    ])
                                                    ->visible(fn (Agent $record): bool => self::hasInternationalAddress($record))
                                                    ->schema([
                                                        Text::make('Dirección en Otros Paises')
                                                            ->icon('heroicon-o-globe-alt')
                                                            ->weight('semibold')
                                                            ->color('info'),
                                                        Grid::make(['default' => 1, 'sm' => 2])
                                                            ->extraAttributes([
                                                                'class' => self::IOS_INSET_GROUP_CLASS,
                                                            ])
                                                            ->schema([
                                                                TextEntry::make('country_other_country')
                                                                    ->label('País')
                                                                    ->icon('heroicon-m-globe-americas')
                                                                    ->badge()
                                                                    ->color('info')
                                                                    ->formatStateUsing(fn (mixed $state): ?string => self::formatOtherCountryName($state))
                                                                    ->placeholder('—'),
                                                                TextEntry::make('state_other_country')
                                                                    ->label('Estado / provincia')
                                                                    ->icon('heroicon-m-map')
                                                                    ->badge()
                                                                    ->color('gray')
                                                                    ->placeholder('—'),
                                                                TextEntry::make('city_other_country')
                                                                    ->label('Ciudad')
                                                                    ->icon('heroicon-m-building-office')
                                                                    ->badge()
                                                                    ->color('gray')
                                                                    ->placeholder('—'),
                                                                TextEntry::make('postal_code_other_country')
                                                                    ->label('Código postal')
                                                                    ->icon('heroicon-m-identification')
                                                                    ->badge()
                                                                    ->color('gray')
                                                                    ->placeholder('—'),
                                                            ]),
                                                        TextEntry::make('address_other_country')
                                                            ->label('Dirección internacional')
                                                            ->icon('heroicon-m-home')
                                                            ->iconColor('info')
                                                            ->weight('semibold')
                                                            ->size(TextSize::Medium)
                                                            ->wrap()
                                                            ->formatStateUsing(fn (?string $state): ?string => self::formatAddress($state))
                                                            ->helperText(fn (Agent $record): ?string => filled(self::internationalLocationSummary($record))
                                                                ? 'Ubicación: '.self::internationalLocationSummary($record)
                                                                : null)
                                                            ->placeholder('Sin dirección internacional registrada'),
                                                        TextEntry::make('international_address_copy')
                                                            ->hiddenLabel()
                                                            ->badge()
                                                            ->color('info')
                                                            ->icon('heroicon-o-clipboard-document')
                                                            ->state('Copiar dirección')
                                                            ->copyable()
                                                            ->copyableState(fn (Agent $record): string => AgentAddressClipboardFormat::international($record))
                                                            ->copyMessage('Formato de correspondencia copiado')
                                                            ->visible(fn (Agent $record): bool => AgentAddressClipboardFormat::canCopyInternational($record)),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Jerarquía')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Jerarquía comercial')
                                    ->description('Master → General → Agente → Subagente. Despliega equipos directos de master o desliza cuando hay más de cinco nodos.')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('hierarchy_diagram')
                                                    ->label('Mapa de jerarquía')
                                                    ->html()
                                                    ->getStateUsing(fn (Agent $record): HtmlString => CommercialHierarchyFlowchart::renderForAgent($record))
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contacto alternativo')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Contacto alternativo')
                                    ->description('Segundo canal de contacto e Instagram.')
                                    ->icon('heroicon-o-phone')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('name_contact_2')
                                                    ->label('Nombre')
                                                    ->icon('heroicon-m-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('email_contact_2')
                                                    ->label('Correo')
                                                    ->icon('heroicon-m-envelope')
                                                    ->copyable()
                                                    ->copyMessage('Correo copiado')
                                                    ->placeholder('—'),
                                                TextEntry::make('phone_contact_2')
                                                    ->label('Teléfono')
                                                    ->icon('heroicon-m-phone')
                                                    ->copyable()
                                                    ->copyMessage('Teléfono copiado')
                                                    ->placeholder('—'),
                                                TextEntry::make('user_instagram')
                                                    ->label('Instagram')
                                                    ->icon('heroicon-m-chat-bubble-left-right')
                                                    ->placeholder('—'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Banca local')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Banca en moneda local')
                                    ->description('Beneficiario, cuenta y pago móvil.')
                                    ->icon('heroicon-o-banknotes')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                    ->extraAttributes([
                                                        'class' => self::IOS_INSET_GROUP_CLASS.' mb-4',
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('local_beneficiary_name')
                                                            ->label('Beneficiario')
                                                            ->icon('heroicon-m-user')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_rif')
                                                            ->label('R.I.F.')
                                                            ->icon('heroicon-m-identification')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_number')
                                                            ->label('Nº cuenta')
                                                            ->icon('heroicon-m-credit-card')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_bank')
                                                            ->label('Banco')
                                                            ->icon('heroicon-m-building-library')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_type')
                                                            ->label('Tipo de cuenta')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_phone_pm')
                                                            ->label('Pago móvil')
                                                            ->icon('heroicon-m-device-phone-mobile')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                    ]),
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                                    ->extraAttributes([
                                                        'class' => self::IOS_INSET_GROUP_CLASS,
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('local_beneficiary_account_number_mon_inter')
                                                            ->label('Cuenta moneda extranjera (local)')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_bank_mon_inter')
                                                            ->label('Banco (inter.)')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_type_mon_inter')
                                                            ->label('Tipo (inter.)')
                                                            ->placeholder('—'),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Banca extranjera')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Banca en moneda extranjera')
                                    ->description('Cuenta internacional, Zelle y datos SWIFT / ACH.')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('extra_beneficiary_name')
                                                    ->label('Beneficiario')
                                                    ->icon('heroicon-m-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_ci_rif')
                                                    ->label('CI / RIF')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_account_number')
                                                    ->label('Nº cuenta')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_account_bank')
                                                    ->label('Banco')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_account_type')
                                                    ->label('Tipo de cuenta')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_route')
                                                    ->label('Routing')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_zelle')
                                                    ->label('Zelle')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_ach')
                                                    ->label('ACH')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_swift')
                                                    ->label('SWIFT')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_aba')
                                                    ->label('ABA')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_address')
                                                    ->label('Dirección')
                                                    ->icon('heroicon-m-map-pin')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Comisiones')
                            ->icon('heroicon-o-calculator')
                            ->schema([
                                Section::make('Comisiones')
                                    ->description('TDEC y TDEV: venta nueva y renovación.')
                                    ->icon('heroicon-o-calculator')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 4])
                                                    ->extraAttributes([
                                                        'class' => self::IOS_INSET_GROUP_CLASS.' mb-4',
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('commission_tdec')
                                                            ->label('TDEC — venta nueva')
                                                            ->icon('heroicon-m-calculator')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->suffix(' %')
                                                            ->weight('medium')
                                                            ->placeholder('—'),
                                                        TextEntry::make('commission_tdec_renewal')
                                                            ->label('TDEC — renovación')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->suffix(' %')
                                                            ->weight('medium')
                                                            ->placeholder('—'),
                                                        TextEntry::make('commission_tdev')
                                                            ->label('TDEV — venta nueva')
                                                            ->icon('heroicon-m-calculator')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->suffix(' %')
                                                            ->weight('medium')
                                                            ->placeholder('—'),
                                                        TextEntry::make('commission_tdev_renewal')
                                                            ->label('TDEV — renovación')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->suffix(' %')
                                                            ->weight('medium')
                                                            ->placeholder('—'),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Notas y auditoría')
                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                            ->schema([
                                Section::make('Notas y auditoría')
                                    ->description('Comentarios internos, observaciones de seguimiento y trazabilidad.')
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(1)
                                                    ->extraAttributes([
                                                        'class' => self::IOS_INSET_GROUP_CLASS,
                                                    ])
                                                    ->schema([
                                                        Text::make('Historial de observaciones')
                                                            ->icon('heroicon-m-clipboard-document-list')
                                                            ->weight('semibold'),
                                                        RepeatableEntry::make('observationCommercialStructures')
                                                            ->hiddenLabel()
                                                            ->table([
                                                                TableColumn::make('Observación')->width('70%'),
                                                                TableColumn::make('Registrado por')->width('15%'),
                                                                TableColumn::make('Fecha y hora')->width('15%'),
                                                            ])
                                                            ->schema([
                                                                TextEntry::make('observation')
                                                                    ->placeholder('—')
                                                                    ->wrap()
                                                                    ->tooltip(fn ($record): ?string => is_string($record->observation ?? null) ? $record->observation : null),
                                                                TextEntry::make('created_by')
                                                                    ->icon('heroicon-m-user')
                                                                    ->placeholder('—'),
                                                                TextEntry::make('registered_at_display')
                                                                    ->icon('heroicon-m-clock')
                                                                    ->getStateUsing(fn ($record): string => self::formatObservationRegisteredAt($record))
                                                                    ->placeholder('—'),
                                                            ])
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Afiliaciones')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Afiliaciones asociadas')
                                    ->description(fn (Agent $record): HtmlString => new HtmlString(
                                        'Afiliaciones donde este agente está asignado. Total: '
                                            .'<span class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/15 px-2.5 py-0.5 text-sm font-semibold text-primary ring-1 ring-primary/20 dark:bg-primary/20">'
                                            .(int) ($record->affiliations?->count() ?? 0)
                                            .'</span>'
                                    ))
                                    ->icon('heroicon-o-document-text')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        RepeatableEntry::make('affiliations')
                                            ->formatStateUsing(fn ($state) => $state ?? collect())
                                            ->label('')
                                            ->placeholder('No hay afiliaciones asociadas a este agente.')
                                            ->extraEntryWrapperAttributes([
                                                'class' => 'rounded-2xl border border-slate-200/70 bg-white/70 px-3 py-3 dark:border-white/10 dark:bg-white/5 sm:px-4 sm:py-4',
                                            ])
                                            ->table([
                                                TableColumn::make('Nº solicitud'),
                                                TableColumn::make('Titular del plan'),
                                                TableColumn::make('Plan afiliado'),
                                                TableColumn::make('Estado'),
                                                TableColumn::make('Fecha'),
                                            ])
                                            ->schema([
                                                TextEntry::make('code')
                                                    ->label('Nº solicitud')
                                                    ->badge()
                                                    ->color('success'),
                                                TextEntry::make('full_name_ti')
                                                    ->label('Titular')
                                                    ->placeholder('—'),
                                                TextEntry::make('plan.description')
                                                    ->label('Plan afiliado')
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estado')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::affiliationStatusColor($state)),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha')
                                                    ->dateTime('d/m/Y H:i'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function formatObservationRegisteredAt(mixed $record): string
    {
        if (! $record instanceof ObservationCommercialStructure) {
            return '—';
        }

        if (filled($record->date)) {
            return (string) $record->date;
        }

        return $record->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—';
    }

    private static function formatAddress(?string $state): ?string
    {
        if (! filled($state)) {
            return null;
        }

        return trim($state);
    }

    private static function formatOtherCountryName(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return CountrySelectOptions::exceptVenezuelaInSpanish()[(int) $state]
            ?? Country::query()->whereKey($state)->value('name');
    }

    private static function hasInternationalAddress(Agent $record): bool
    {
        return filled($record->country_other_country)
            || filled($record->state_other_country)
            || filled($record->city_other_country)
            || filled($record->postal_code_other_country)
            || filled($record->address_other_country);
    }

    private static function venezuelaLocationSummary(Agent $record): ?string
    {
        $parts = array_values(array_filter([
            $record->city?->definition,
            $record->state?->definition,
            $record->country?->name,
        ], fn (?string $part): bool => filled($part)));

        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function internationalLocationSummary(Agent $record): ?string
    {
        $parts = array_values(array_filter([
            $record->city_other_country,
            $record->state_other_country,
            self::formatOtherCountryName($record->country_other_country),
        ], fn (?string $part): bool => filled($part)));

        if ($parts === []) {
            return null;
        }

        $summary = implode(', ', $parts);

        if (filled($record->postal_code_other_country)) {
            $summary .= ' · CP '.$record->postal_code_other_country;
        }

        return $summary;
    }
}

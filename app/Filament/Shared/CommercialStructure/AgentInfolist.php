<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Models\Agent;
use App\Models\ObservationCommercialStructure;
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
use Illuminate\Support\HtmlString;

class AgentInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_INSET_GROUP_CLASS = 'rounded-xl border border-slate-200/60 bg-slate-50/50 p-3 dark:border-white/10 dark:bg-white/[0.04] sm:p-4';

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
                                                        TextEntry::make('address')
                                                            ->label('Dirección')
                                                            ->icon('heroicon-m-home')
                                                            ->placeholder('—')
                                                            ->columnSpanFull(),

                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Jerarquía')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Jerarquía comercial')
                                    ->description('Diagrama visual para validar si la agencia es general, master y su relación con TUDRENCASA.')
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
}

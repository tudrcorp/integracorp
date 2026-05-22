<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Models\Agency;
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

class AgencyInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_INSET_GROUP_CLASS = 'rounded-xl border border-slate-200/60 bg-slate-50/50 p-3 dark:border-white/10 dark:bg-white/[0.04] sm:p-4';

    private static function agencyStatusColor(?string $state): string
    {
        return match ($state) {
            'ACTIVO' => 'success',
            'INACTIVO' => 'danger',
            'POR REVISION' => 'warning',
            default => 'gray',
        };
    }

    private static function formatYesNo(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        return filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('agencyInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información general')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Información general de la agencia')
                                    ->description('Razón social, RIF, contacto y representante legal.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(4)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('code')
                                                    ->label('Código')
                                                    ->icon('heroicon-m-qr-code')
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('owner_code')
                                                    ->label('Código jerarquía / pertenece a')
                                                    ->icon('heroicon-m-link')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('accountManager.full_name')
                                                    ->label('Account manager')
                                                    ->icon('heroicon-m-user-circle')
                                                    ->weight('medium')
                                                    ->placeholder('Sin asignar'),
                                                TextEntry::make('status')
                                                    ->label('Estatus')
                                                    ->icon('heroicon-m-signal')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::agencyStatusColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('name_corporative')
                                                    ->label('Razón social')
                                                    ->size('lg')
                                                    ->weight('semibold')
                                                    ->color('gray')
                                                    ->placeholder('Sin razón social'),
                                                TextEntry::make('rif')
                                                    ->label('RIF')
                                                    ->icon('heroicon-m-identification')
                                                    ->copyable()
                                                    ->copyMessage('RIF copiado')
                                                    ->placeholder('—'),
                                                TextEntry::make('email')
                                                    ->label('Correo electrónico')
                                                    ->icon('heroicon-m-envelope')
                                                    ->copyable()
                                                    ->copyMessage('Correo copiado al portapapeles')
                                                    ->placeholder('—'),
                                                TextEntry::make('phone')
                                                    ->label('Teléfono')
                                                    ->icon('heroicon-m-phone')
                                                    ->copyable()
                                                    ->copyMessage('Teléfono copiado')
                                                    ->placeholder('—'),
                                                TextEntry::make('name_representative')
                                                    ->label('Representante')
                                                    ->icon('heroicon-m-user')
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('ci_responsable')
                                                    ->label('Cédula del responsable')
                                                    ->icon('heroicon-m-identification')
                                                    ->copyable()
                                                    ->copyMessage('Cédula copiada')
                                                    ->placeholder('—'),
                                                TextEntry::make('brithday_date')
                                                    ->label('Fecha de nacimiento del representante')
                                                    ->icon('heroicon-m-cake')
                                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('anniversary_date')
                                                    ->label('Aniversario de la agencia')
                                                    ->icon('heroicon-m-calendar-days')
                                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('user_instagram')
                                                    ->label('Instagram')
                                                    ->icon('heroicon-m-chat-bubble-left-right')
                                                    ->placeholder('—'),
                                                TextEntry::make('date_register')
                                                    ->label('Fecha de registro')
                                                    ->icon('heroicon-m-calendar-days')
                                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('country.name')
                                                    ->label('País')
                                                    ->icon('heroicon-m-globe-americas')
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado')
                                                    ->icon('heroicon-m-map')
                                                    ->placeholder('—'),
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->icon('heroicon-m-building-office')
                                                    ->placeholder('—'),
                                                TextEntry::make('region')
                                                    ->label('Región')
                                                    ->icon('heroicon-m-map-pin')
                                                    ->placeholder('—'),
                                                TextEntry::make('address')
                                                    ->label('Dirección')
                                                    ->icon('heroicon-m-home')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
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
                                                    ->getStateUsing(fn (Agency $record): HtmlString => CommercialHierarchyFlowchart::renderForAgency($record))
                                                    ->columnSpanFull(),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contacto alternativo')
                            ->icon('heroicon-o-user-plus')
                            ->schema([
                                Section::make('Contacto alternativo')
                                    ->description('Datos de una segunda persona o canal de contacto.')
                                    ->icon('heroicon-o-user-plus')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(5)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('name_contact_2')
                                                    ->label('Nombre / razón social')
                                                    ->icon('heroicon-m-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('email_contact_2')
                                                    ->label('Correo secundario')
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
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Banca nacional')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Datos bancarios en moneda nacional')
                                    ->description('Beneficiario y cuentas en bolívares y divisas locales.')
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
                                                Text::make('Cuenta nacional (Bs.)')
                                                    ->weight('semibold')
                                                    ->color('gray'),
                                                Grid::make(6)
                                                    ->schema([
                                                        TextEntry::make('local_beneficiary_name')
                                                            ->label('Beneficiario')
                                                            ->icon('heroicon-m-user')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_rif')
                                                            ->label('CI / RIF')
                                                            ->icon('heroicon-m-identification')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_number')
                                                            ->label('Número de cuenta')
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
                                                Text::make('Cuenta nacional, moneda internacional (US$, EUR)')
                                                    ->weight('semibold')
                                                    ->color('gray')
                                                    ->extraAttributes(['class' => 'mt-4']),
                                                Grid::make(6)
                                                    ->schema([
                                                        TextEntry::make('local_beneficiary_account_number_mon_inter')
                                                            ->label('Número de cuenta')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_bank_mon_inter')
                                                            ->label('Banco')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_type_mon_inter')
                                                            ->label('Tipo de cuenta')
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
                                Section::make('Datos bancarios en moneda extranjera')
                                    ->description('Cuenta internacional del beneficiario.')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(5)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('extra_beneficiary_name')
                                                    ->label('Beneficiario')
                                                    ->icon('heroicon-m-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_ci_rif')
                                                    ->label('CI / RIF / ID')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_account_number')
                                                    ->label('Número de cuenta')
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
                                                    ->label('Dirección del beneficiario')
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
                                Section::make('Comisiones TDEC / TDEV')
                                    ->description('Porcentajes y activación de esquemas.')
                                    ->icon('heroicon-o-calculator')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(4)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('commission_tdec')
                                                    ->label('Comisión TDEC')
                                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('commission_tdec_renewal')
                                                    ->label('Comisión renovación TDEC')
                                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('commission_tdev')
                                                    ->label('Comisión TDEV')
                                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('commission_tdev_renewal')
                                                    ->label('Comisión renovación TDEV')
                                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Bitácora')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Section::make('Bitácora')
                                    ->description('Notas del analista sobre reuniones y contactos con la agencia.')
                                    ->icon('heroicon-o-clipboard-document-list')
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
                    ]),
            ]);
    }

    private static function formatPercent(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        return number_format((float) $state, 2, ',', '.').' %';
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

<?php

declare(strict_types=1);

namespace App\Filament\Agents\Resources\Agents\Schemas;

use App\Support\FilamentDateDisplay;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('agentsAgentInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información Personal')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Información Personal')
                                    ->description('Información principal del subagente')
                                    ->icon(Heroicon::OutlinedUser)
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
                                                    ->label('Nombre / Razón social')
                                                    ->weight('semibold')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                                    ->schema([
                                                        TextEntry::make('ci')
                                                            ->label('Cédula de identidad')
                                                            ->icon(Heroicon::OutlinedIdentification)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('rif')
                                                            ->label('RIF')
                                                            ->icon(Heroicon::OutlinedIdentification)
                                                            ->formatStateUsing(fn (?string $state): ?string => filled($state) ? 'J-'.$state : null)
                                                            ->placeholder('—'),
                                                        TextEntry::make('birth_date')
                                                            ->label('Fecha de nacimiento')
                                                            ->icon(Heroicon::OutlinedCalendar)
                                                            ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('sex')
                                                            ->label('Sexo')
                                                            ->badge()
                                                            ->color('info')
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->label('Correo electrónico')
                                                            ->icon(Heroicon::OutlinedEnvelope)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone')
                                                            ->label('Teléfono')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('user_tdev')
                                                            ->label('Usuario TDEV')
                                                            ->placeholder('—'),
                                                        TextEntry::make('user_instagram')
                                                            ->label('Usuario Instagram')
                                                            ->icon(Heroicon::OutlinedAtSymbol)
                                                            ->placeholder('—'),
                                                        TextEntry::make('code_agent')
                                                            ->label('Código agente')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->formatStateUsing(fn (?string $state, $record): string => filled($state)
                                                                ? (string) $state
                                                                : 'AGT-000'.($record->id ?? ''))
                                                            ->badge()
                                                            ->color('primary')
                                                            ->placeholder('—'),
                                                        TextEntry::make('owner_code')
                                                            ->label('Código propietario')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->placeholder('—'),
                                                        TextEntry::make('status')
                                                            ->label('Estado')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::agentStatusColor($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('country.name')
                                                            ->label('País')
                                                            ->icon(Heroicon::OutlinedGlobeAlt)
                                                            ->placeholder('—'),
                                                        TextEntry::make('state.definition')
                                                            ->label('Estado')
                                                            ->icon(Heroicon::OutlinedMap)
                                                            ->placeholder('—'),
                                                        TextEntry::make('city.definition')
                                                            ->label('Ciudad')
                                                            ->icon(Heroicon::OutlinedBuildingOffice2)
                                                            ->placeholder('—'),
                                                        TextEntry::make('region')
                                                            ->label('Región')
                                                            ->placeholder('—'),
                                                        TextEntry::make('address')
                                                            ->label('Dirección')
                                                            ->icon(Heroicon::OutlinedMapPin)
                                                            ->wrap()
                                                            ->columnSpanFull()
                                                            ->placeholder('—'),
                                                        TextEntry::make('name_contact_2')
                                                            ->label('Contacto alternativo')
                                                            ->placeholder('—'),
                                                        TextEntry::make('email_contact_2')
                                                            ->label('Correo alternativo')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone_contact_2')
                                                            ->label('Teléfono alternativo')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_by')
                                                            ->label('Registrado por')
                                                            ->placeholder('—'),
                                                        TextEntry::make('date_register')
                                                            ->label('Fecha de registro')
                                                            ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->label('Creado en sistema')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                        TextEntry::make('updated_at')
                                                            ->label('Última actualización')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Comisiones')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->schema([
                                Section::make('Comisiones')
                                    ->description('Porcentajes TDEC y TDEV')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                                    ->extraAttributes([
                                                        'class' => self::IOS_INSET_GROUP_CLASS,
                                                    ])
                                                    ->schema([
                                                        IconEntry::make('tdec')
                                                            ->label('TDEC habilitado')
                                                            ->boolean(),
                                                        IconEntry::make('tdev')
                                                            ->label('TDEV habilitado')
                                                            ->boolean(),
                                                        TextEntry::make('commission_tdec')
                                                            ->label('Comisión TDEC')
                                                            ->suffix(' %')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->placeholder('—'),
                                                        TextEntry::make('commission_tdec_renewal')
                                                            ->label('Comisión renovación TDEC')
                                                            ->suffix(' %')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->placeholder('—'),
                                                        TextEntry::make('commission_tdev')
                                                            ->label('Comisión TDEV')
                                                            ->suffix(' %')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->placeholder('—'),
                                                        TextEntry::make('commission_tdev_renewal')
                                                            ->label('Comisión renovación TDEV')
                                                            ->suffix(' %')
                                                            ->numeric(decimalPlaces: 2)
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Información Bancaria Local(VES)')
                            ->icon(Heroicon::OutlinedBuildingLibrary)
                            ->schema([
                                Section::make('Información Bancaria Local(VES)')
                                    ->description('Datos bancarios en moneda nacional')
                                    ->icon(Heroicon::OutlinedBuildingLibrary)
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
                                                        'class' => self::IOS_INSET_GROUP_CLASS,
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('local_beneficiary_name')
                                                            ->label('Beneficiario')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_rif')
                                                            ->label('RIF beneficiario')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_number')
                                                            ->label('Número de cuenta')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_bank')
                                                            ->label('Banco')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_account_type')
                                                            ->label('Tipo de cuenta')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_phone_pm')
                                                            ->label('Pago móvil')
                                                            ->copyable()
                                                            ->placeholder('—'),
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
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Información Bancaria Extra(US$)')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->schema([
                                Section::make('Información Bancaria Extra(US$)')
                                    ->description('Datos bancarios en moneda extranjera')
                                    ->icon(Heroicon::OutlinedGlobeAlt)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('extra_beneficiary_name')
                                                    ->label('Beneficiario')
                                                    ->placeholder('—'),
                                                TextEntry::make('extra_beneficiary_ci_rif')
                                                    ->label('CI / RIF')
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
                                                    ->label('Dirección')
                                                    ->wrap()
                                                    ->columnSpanFull()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Acuerdo y condiciones')
                            ->icon(Heroicon::OutlinedDocumentCheck)
                            ->schema([
                                Section::make('Acuerdo y condiciones')
                                    ->description('Aceptación de términos y documentación')
                                    ->icon(Heroicon::OutlinedDocumentCheck)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                IconEntry::make('is_accepted_conditions')
                                                    ->label('Condiciones aceptadas')
                                                    ->boolean(),
                                                TextEntry::make('fir_dig_agent')
                                                    ->label('Firma digital agente')
                                                    ->placeholder('—'),
                                                TextEntry::make('fir_dig_agency')
                                                    ->label('Firma digital agencia')
                                                    ->placeholder('—'),
                                                TextEntry::make('file_ci_rif')
                                                    ->label('Documento CI/RIF')
                                                    ->placeholder('—'),
                                                TextEntry::make('file_w8_w9')
                                                    ->label('Documento W8/W9')
                                                    ->placeholder('—'),
                                                TextEntry::make('file_account_usd')
                                                    ->label('Cuenta USD')
                                                    ->placeholder('—'),
                                                TextEntry::make('file_account_bsd')
                                                    ->label('Cuenta BSD')
                                                    ->placeholder('—'),
                                                TextEntry::make('file_account_zelle')
                                                    ->label('Cuenta Zelle')
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

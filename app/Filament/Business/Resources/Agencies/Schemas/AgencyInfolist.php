<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agencies\Schemas;

use App\Models\ObservationCommercialStructure;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class AgencyInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

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

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Agencia')
                    ->description('Identidad comercial, tipo y estado operativo.')
                    ->icon('heroicon-o-building-library')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('name_corporative')
                                    ->label('Razón social')
                                    ->size('lg')
                                    ->weight('semibold')
                                    ->color('gray')
                                    ->placeholder('Sin razón social'),
                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->schema([
                                        TextEntry::make('code')
                                            ->label('Código')
                                            ->icon('heroicon-m-qr-code')
                                            ->badge()
                                            ->color('success')
                                            ->placeholder('—'),
                                        TextEntry::make('typeAgency.definition')
                                            ->label('Tipo')
                                            ->icon('heroicon-m-tag')
                                            ->badge()
                                            ->color('info')
                                            ->placeholder('—'),
                                        TextEntry::make('status')
                                            ->label('Estatus')
                                            ->icon('heroicon-m-signal')
                                            ->badge()
                                            ->color(fn (?string $state): string => self::agencyStatusColor($state))
                                            ->placeholder('—'),
                                        TextEntry::make('accountManager.full_name')
                                            ->label('Account manager')
                                            ->icon('heroicon-m-user-circle')
                                            ->weight('medium')
                                            ->placeholder('Sin asignar'),
                                        TextEntry::make('date_register')
                                            ->label('Registro')
                                            ->icon('heroicon-m-calendar-days')
                                            ->date('d/m/Y')
                                            ->placeholder('—'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Representación y documentos')
                    ->description('Representante legal e identificación fiscal.')
                    ->icon('heroicon-o-identification')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('name_representative')
                                    ->label('Representante')
                                    ->icon('heroicon-m-user')
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('rif')
                                    ->label('RIF')
                                    ->icon('heroicon-m-identification')
                                    ->copyable()
                                    ->copyMessage('RIF copiado')
                                    ->placeholder('—'),
                                TextEntry::make('ci_responsable')
                                    ->label('Cédula del responsable')
                                    ->icon('heroicon-m-identification')
                                    ->copyable()
                                    ->copyMessage('Cédula copiada')
                                    ->placeholder('—'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto y ubicación')
                    ->description('Canales directos y domicilio fiscal.')
                    ->icon('heroicon-o-map-pin')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                Grid::make(1)
                                    ->extraAttributes([
                                        'class' => self::IOS_INSET_GROUP_CLASS,
                                    ])
                                    ->schema([
                                        TextEntry::make('email')
                                            ->label('Correo')
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
                                        TextEntry::make('user_instagram')
                                            ->label('Instagram')
                                            ->icon('heroicon-m-chat-bubble-left-right')
                                            ->placeholder('—'),
                                    ]),
                                Grid::make(1)
                                    ->extraAttributes([
                                        'class' => self::IOS_INSET_GROUP_CLASS,
                                    ])
                                    ->schema([
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
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto alternativo')
                    ->description('Segundo canal de contacto cuando aplica.')
                    ->icon('heroicon-o-phone')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
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
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Banca en moneda local')
                    ->description('Beneficiario, cuentas en bolívares y datos de pago móvil.')
                    ->icon('heroicon-o-banknotes')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
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
                                            ->label('RIF')
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

                Section::make('Banca en moneda extranjera')
                    ->description('Transferencias internacionales y datos SWIFT / ACH.')
                    ->icon('heroicon-o-currency-dollar')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
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

                Section::make('Comisiones')
                    ->description('TDEC, TDEV y porcentajes de comisión y renovación.')
                    ->icon('heroicon-o-calculator')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('tdec')
                                    ->label('TDEC')
                                    ->icon('heroicon-m-calculator')
                                    ->numeric(2)
                                    ->placeholder('—'),
                                TextEntry::make('tdev')
                                    ->label('TDEV')
                                    ->icon('heroicon-m-calculator')
                                    ->numeric(2)
                                    ->placeholder('—'),
                                TextEntry::make('commission_tdec')
                                    ->label('Comisión TDEC')
                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('commission_tdec_renewal')
                                    ->label('Comisión TDEC renovación')
                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('commission_tdev')
                                    ->label('Comisión TDEV')
                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                    ->weight('medium')
                                    ->placeholder('—'),
                                TextEntry::make('commission_tdev_renewal')
                                    ->label('Comisión TDEV renovación')
                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state))
                                    ->weight('medium')
                                    ->placeholder('—'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Notas y auditoría')
                    ->description('Comentarios internos, observaciones de seguimiento y trazabilidad.')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
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
                                                TableColumn::make('Observación')->width('38%'),
                                                TableColumn::make('Registrado por')->width('18%'),
                                                TableColumn::make('Última edición')->width('18%'),
                                                TableColumn::make('Fecha y hora')->width('26%'),
                                            ])
                                            ->schema([
                                                TextEntry::make('observation')
                                                    ->placeholder('—')
                                                    ->limit(180)
                                                    ->tooltip(fn ($record): ?string => is_string($record->observation ?? null) ? $record->observation : null),
                                                TextEntry::make('created_by')
                                                    ->icon('heroicon-m-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('updated_by')
                                                    ->label('Última edición')
                                                    ->icon('heroicon-m-pencil-square')
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

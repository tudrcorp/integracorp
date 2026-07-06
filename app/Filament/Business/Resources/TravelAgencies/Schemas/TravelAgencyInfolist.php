<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TravelAgencies\Schemas;

use App\Models\TravelAgency;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TravelAgencyInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('travelAgencyInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Marca')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                Section::make('Brand Logo')
                                    ->description('Logo de la agencia de viajes.')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                ImageEntry::make('logo')
                                                    ->label('Logo')
                                                    ->disk('public')
                                                    ->visibility('public')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Información general')
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->schema([
                                Section::make('Informacion General')
                                    ->description('Datos principales, contacto y ubicación.')
                                    ->icon(Heroicon::OutlinedPaperAirplane)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2, 'xl' => 4])
                                                    ->schema([
                                                        TextEntry::make('name')
                                                            ->label('Nombre de la Agencia')
                                                            ->weight('semibold')
                                                            ->columnSpan(['default' => 1, 'xl' => 2])
                                                            ->placeholder('—'),
                                                        TextEntry::make('numberIdentification')
                                                            ->label('Número de identificación')
                                                            ->prefix('J/V/E-')
                                                            ->placeholder('—'),
                                                        TextEntry::make('status')
                                                            ->label('Estado')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::statusColor($state))
                                                            ->formatStateUsing(fn (?string $state): string => strtoupper((string) ($state ?? 'SIN ESTADO')))
                                                            ->placeholder('—'),
                                                        TextEntry::make('aniversary')
                                                            ->label('Fecha aniversario')
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatLegacyDate($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('representante')
                                                            ->label('Representante')
                                                            ->placeholder('—'),
                                                        TextEntry::make('idRepresentante')
                                                            ->label('ID representante')
                                                            ->placeholder('—'),
                                                        TextEntry::make('FechaNacimientoRepresentante')
                                                            ->label('Fecha nacimiento representante')
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatLegacyDate($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone')
                                                            ->label('Teléfono')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->copyMessage('Copiado')
                                                            ->url(fn (TravelAgency $record): ?string => filled($record->phone) ? 'tel:'.$record->phone : null)
                                                            ->placeholder('—'),
                                                        TextEntry::make('phoneAdditional')
                                                            ->label('Teléfono adicional')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->label('Correo electrónico')
                                                            ->icon(Heroicon::OutlinedEnvelope)
                                                            ->copyable()
                                                            ->url(fn (TravelAgency $record): ?string => filled($record->email) ? 'mailto:'.$record->email : null)
                                                            ->placeholder('—'),
                                                        TextEntry::make('userInstagram')
                                                            ->label('Instagram')
                                                            ->icon(Heroicon::OutlinedAtSymbol)
                                                            ->placeholder('—'),
                                                        TextEntry::make('country.name')
                                                            ->label('País')
                                                            ->icon(Heroicon::OutlinedGlobeAlt)
                                                            ->placeholder('—'),
                                                        TextEntry::make('state.definition')
                                                            ->label('Estado')
                                                            ->placeholder('—'),
                                                        TextEntry::make('city.definition')
                                                            ->label('Ciudad')
                                                            ->placeholder('—'),
                                                        TextEntry::make('address')
                                                            ->label('Dirección')
                                                            ->columnSpan(['default' => 1, 'xl' => 2])
                                                            ->placeholder('—'),
                                                        TextEntry::make('userPortalWeb')
                                                            ->label('Usuario portal web')
                                                            ->placeholder('—'),
                                                        TextEntry::make('fechaIngreso')
                                                            ->label('Fecha de ingreso')
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatLegacyDate($state))
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Contactos')
                            ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                            ->schema([
                                Section::make('Contacto del Area Administrativa')
                                    ->description('Contacto secundario del área administrativa.')
                                    ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2, 'xl' => 4])
                                                    ->schema([
                                                        TextEntry::make('nameSecundario')
                                                            ->label('Nombre / Razón social')
                                                            ->placeholder('—'),
                                                        TextEntry::make('emailSecundario')
                                                            ->label('Correo')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('phoneSecundario')
                                                            ->label('Teléfono')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('fechaNacimientoSecundario')
                                                            ->label('Fecha de nacimiento')
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatLegacyDate($state))
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Agentes')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                Section::make('Agentes asociados')
                                    ->description('Listado de agentes vinculados a la agencia de viajes.')
                                    ->icon(Heroicon::OutlinedUserGroup)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('travelAgents')
                                                    ->hiddenLabel()
                                                    ->table([
                                                        TableColumn::make('Nombre y Apellido')->width('22%'),
                                                        TableColumn::make('Cargo')->width('18%'),
                                                        TableColumn::make('Correo')->width('22%'),
                                                        TableColumn::make('Teléfono')->width('18%'),
                                                        TableColumn::make('Fecha nac.')->width('20%'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('name')
                                                            ->placeholder('—'),
                                                        TextEntry::make('cargo')
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('fechaNacimiento')
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatLegacyDate($state))
                                                            ->placeholder('—'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Jerarquía')
                            ->icon(Heroicon::OutlinedAdjustmentsVertical)
                            ->schema([
                                Section::make('Información Jerarquica')
                                    ->description('Clasificación comercial, comisiones y niveles.')
                                    ->icon(Heroicon::OutlinedAdjustmentsVertical)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2, 'xl' => 4])
                                                    ->schema([
                                                        TextEntry::make('classification')
                                                            ->label('Clasificación')
                                                            ->badge()
                                                            ->color('info')
                                                            ->placeholder('—'),
                                                        TextEntry::make('comision')
                                                            ->label('Comisión (%)')
                                                            ->suffix('%')
                                                            ->placeholder('—'),
                                                        TextEntry::make('montoCreditoAprobado')
                                                            ->label('Monto crédito aprobado')
                                                            ->numeric()
                                                            ->placeholder('—'),
                                                        TextEntry::make('nivel')
                                                            ->label('Nivel')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->placeholder('—'),
                                                        TextEntry::make('agenteSuperiorNivel3')
                                                            ->label('Agente superior nivel 3')
                                                            ->placeholder('—'),
                                                        TextEntry::make('agenciaSuperiorNivel2')
                                                            ->label('Agencia superior nivel 2')
                                                            ->placeholder('—'),
                                                        TextEntry::make('agenciaPpalNivel1')
                                                            ->label('Agencia principal nivel 1')
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Bancos nacionales')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                Section::make('DATOS BANCARIOS MONEDA NACIONAL')
                                    ->description('Beneficiario y cuentas en moneda nacional.')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2, 'xl' => 3])
                                                    ->schema([
                                                        TextEntry::make('local_beneficiary_name')
                                                            ->label('Beneficiario')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_rif')
                                                            ->label('CI/RIF')
                                                            ->placeholder('—'),
                                                        TextEntry::make('local_beneficiary_phone_pm')
                                                            ->label('Pago móvil')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                    ]),
                                                Section::make('Cuenta nacional (Bs.)')
                                                    ->compact()
                                                    ->schema([
                                                        Grid::make()
                                                            ->columns(['default' => 1, 'lg' => 3])
                                                            ->schema([
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
                                                            ]),
                                                    ]),
                                                Section::make('Cuenta nacional (US$, EUR)')
                                                    ->compact()
                                                    ->schema([
                                                        Grid::make()
                                                            ->columns(['default' => 1, 'lg' => 3])
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
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Bancos extranjeros')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->schema([
                                Section::make('DATOS BANCARIOS MONEDA EXTRANJERA')
                                    ->description('Cuentas y datos bancarios internacionales.')
                                    ->icon(Heroicon::OutlinedCurrencyDollar)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2, 'xl' => 4])
                                                    ->schema([
                                                        TextEntry::make('extra_beneficiary_name')
                                                            ->label('Beneficiario')
                                                            ->placeholder('—'),
                                                        TextEntry::make('extra_beneficiary_ci_rif')
                                                            ->label('CI/RIF/ID/Pasaporte')
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
                                                        TextEntry::make('extra_beneficiary_address')
                                                            ->label('Dirección')
                                                            ->columnSpan(['default' => 1, 'xl' => 2])
                                                            ->placeholder('—'),
                                                        TextEntry::make('extra_beneficiary_route')
                                                            ->label('Ruta')
                                                            ->placeholder('—'),
                                                        TextEntry::make('extra_beneficiary_swift')
                                                            ->label('Swift')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('extra_beneficiary_zelle')
                                                            ->label('Zelle')
                                                            ->copyable()
                                                            ->placeholder('—'),
                                                        TextEntry::make('extra_beneficiary_ach')
                                                            ->label('ACH')
                                                            ->placeholder('—'),
                                                        TextEntry::make('extra_beneficiary_aba')
                                                            ->label('ABA')
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Observaciones')
                            ->icon(Heroicon::OutlinedFolderPlus)
                            ->schema([
                                Section::make('OBSERVACIONES')
                                    ->description('Reuniones, contactos y notas del analista.')
                                    ->icon(Heroicon::OutlinedFolderPlus)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('observationCommercialStructures')
                                                    ->hiddenLabel()
                                                    ->table([
                                                        TableColumn::make('Observación')->width('50%'),
                                                        TableColumn::make('Responsable')->width('25%'),
                                                        TableColumn::make('Fecha')->width('25%'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('observation')
                                                            ->limit(120)
                                                            ->tooltip(fn ($record): ?string => is_string($record->observation ?? null) ? $record->observation : null)
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_by')
                                                            ->placeholder('—'),
                                                        TextEntry::make('date')
                                                            ->placeholder('—'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Auditoría')
                            ->icon(Heroicon::OutlinedClock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->description('Registro de altas y últimas modificaciones.')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextEntry::make('created_by')
                                                            ->label('Creado por')
                                                            ->placeholder('—'),
                                                        TextEntry::make('updated_by')
                                                            ->label('Actualizado por')
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha de registro')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->placeholder('—'),
                                                        TextEntry::make('updated_at')
                                                            ->label('Última actualización')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->icon(Heroicon::OutlinedArrowPath)
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    private static function statusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'APROBADO', 'APROBADA' => 'success',
            'INACTIVO', 'INACTIVA', 'SUSPENDIDO', 'RECHAZADO' => 'danger',
            'PENDIENTE', 'POR REVISAR', 'EN REVISIÓN', 'EN REVISION' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Algunas fechas se guardan como texto `d/m/Y`; `Carbon::parse()` falla con ese formato.
     */
    private static function formatLegacyDate(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if ($state instanceof \Carbon\CarbonInterface) {
            return $state->format('d/m/Y');
        }

        $value = trim((string) $state);

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            return $value;
        }

        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('d/m/Y');
        } catch (\Throwable) {
            // Continúa con el intento genérico abajo.
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies\Schemas;

use App\Models\Company;
use App\Support\Companies\CompanyAssociateRegistrar;
use App\Support\Companies\CompanyResponsibleDays;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompanyInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private static function planStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'APROBADA', 'APROBADO' => 'success',
            'PRE-APROBADO' => 'warning',
            'INACTIVO', 'INACTIVA' => 'gray',
            default => 'gray',
        };
    }

    private static function utilizationColor(Company $record): string
    {
        $population = CompanyResponsibleDays::populationTotalFor($record->planGenerator);
        $contracted = (int) ($record->responsibles_sum_contracted_days ?? $record->responsibles->sum('contracted_days'));

        if ($population === null) {
            return 'gray';
        }

        if ($contracted > $population) {
            return 'danger';
        }

        if ($contracted === $population) {
            return 'success';
        }

        return 'info';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('companyInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->id('company-infolist-tabs')
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Empresa')
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->schema([
                                self::companySection(),
                            ]),
                        Tab::make('Cotización')
                            ->icon(Heroicon::OutlinedTableCells)
                            ->schema([
                                self::planSection(),
                            ]),
                        Tab::make('Responsables')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                self::responsiblesSection(),
                            ]),
                        Tab::make('Enlace público')
                            ->icon(Heroicon::OutlinedLink)
                            ->schema([
                                self::publicRegistrationSection(),
                            ]),
                        Tab::make('Auditoría')
                            ->icon(Heroicon::OutlinedClock)
                            ->schema([
                                self::auditSection(),
                            ]),
                    ]),
            ]);
    }

    private static function companySection(): Section
    {
        return Section::make('Datos de la empresa')
            ->description('Información fiscal y de contacto del nuevo negocio.')
            ->icon(Heroicon::OutlinedBuildingOffice2)
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
                                TextEntry::make('name')
                                    ->label('Nombre / Razón Social')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->weight('semibold')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('rif')
                                    ->label('RIF')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->badge()
                                    ->color('gray')
                                    ->copyable(),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->label('Correo electrónico')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('address')
                                    ->label('Dirección')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                            ]),
                    ]),
            ]);
    }

    private static function planSection(): Section
    {
        return Section::make('Cotización / Plan asociado')
            ->description('Plan generado vinculado a este negocio y métricas de población.')
            ->icon(Heroicon::OutlinedTableCells)
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
                            ->columns(['default' => 1, 'lg' => 3])
                            ->schema([
                                TextEntry::make('planGenerator.control_number')
                                    ->label('Nro. Control')
                                    ->icon(Heroicon::OutlinedHashtag)
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('Sin plan'),
                                TextEntry::make('planGenerator.name')
                                    ->label('Plan / Cotización')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->weight('semibold')
                                    ->placeholder('Sin plan')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('planGenerator.status')
                                    ->label('Estatus del plan')
                                    ->badge()
                                    ->color(fn (?string $state): string => self::planStatusColor($state))
                                    ->placeholder('—'),
                                TextEntry::make('planGenerator.client_data')
                                    ->label('Cliente en cotización')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('planGenerator.agent_name')
                                    ->label('Agente')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->placeholder('—'),
                                TextEntry::make('planGenerator.issued_at')
                                    ->label('Fecha de emisión')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                                TextEntry::make('planGenerator.population_summary')
                                    ->label('Población (texto)')
                                    ->icon(Heroicon::OutlinedUsers)
                                    ->placeholder('—'),
                                TextEntry::make('population_total')
                                    ->label('Población total')
                                    ->icon(Heroicon::OutlinedUsers)
                                    ->state(fn (Company $record): ?int => CompanyResponsibleDays::populationTotalFor($record->planGenerator))
                                    ->formatStateUsing(fn (?int $state): string => $state !== null
                                        ? number_format($state, 0, ',', '.').' personas'
                                        : '—')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('responsibles_sum_contracted_days')
                                    ->label('Días contratados')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->formatStateUsing(fn ($state): string => number_format((int) ($state ?? 0), 0, ',', '.'))
                                    ->badge()
                                    ->color(fn (Company $record): string => self::utilizationColor($record)),
                                TextEntry::make('population_utilization')
                                    ->label('Uso de población')
                                    ->icon(Heroicon::OutlinedChartBar)
                                    ->state(function (Company $record): string {
                                        $population = CompanyResponsibleDays::populationTotalFor($record->planGenerator);
                                        $contracted = (int) ($record->responsibles_sum_contracted_days ?? $record->responsibles->sum('contracted_days'));

                                        if ($population === null) {
                                            return '—';
                                        }

                                        $percentage = $population > 0
                                            ? min(999, (int) round(($contracted / $population) * 100))
                                            : 0;

                                        return number_format($contracted, 0, ',', '.').' / '.number_format($population, 0, ',', '.')." ({$percentage}%)";
                                    })
                                    ->badge()
                                    ->color(fn (Company $record): string => self::utilizationColor($record))
                                    ->columnSpan(['default' => 1, 'lg' => 3]),
                            ]),
                    ]),
            ]);
    }

    private static function responsiblesSection(): Section
    {
        return Section::make('Responsables y asociados')
            ->description('Cada responsable con el detalle de los usuarios, asociados o clientes registrados bajo su cédula.')
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
                        View::make('filament.business.companies.responsibles-associates-panel')
                            ->viewData(fn (Company $record): array => [
                                'companyId' => $record->getKey(),
                            ]),
                    ]),
            ]);
    }

    private static function publicRegistrationSection(): Section
    {
        return Section::make('Enlace de registro público')
            ->description('Comparta este enlace con los responsables para que registren a sus asociados, clientes o usuarios.')
            ->icon(Heroicon::OutlinedLink)
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
                                TextEntry::make('public_registration_url')
                                    ->label('URL pública')
                                    ->state(fn (Company $record): string => CompanyAssociateRegistrar::publicRegistrationUrl($record))
                                    ->copyable()
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('associates_count')
                                    ->label('Asociados registrados')
                                    ->state(fn (Company $record): int => (int) ($record->associates_count ?? $record->associates()->count()))
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('registration_token')
                                    ->label('Token')
                                    ->badge()
                                    ->color('gray')
                                    ->copyable(),
                            ]),
                    ]),
            ]);
    }

    private static function auditSection(): Section
    {
        return Section::make('Auditoría')
            ->description('Trazabilidad del registro del negocio.')
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
                            ->columns(['default' => 1, 'lg' => 3])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Registrado por')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}

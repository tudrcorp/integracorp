<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Agencies\Schemas;

use App\Models\Agency;
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
                    ->tabs([
                        Tab::make('Información general')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Información general de la agencia')
                                    ->description('Razón social, RIF, contacto y representante legal.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
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
                                        'class' => self::IOS_SECTION_CLASS,
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
                                                    ->getStateUsing(fn (Agency $record): HtmlString => self::renderHierarchyDiagram($record))
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
                                        'class' => self::IOS_SECTION_CLASS,
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
                                        'class' => self::IOS_SECTION_CLASS,
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
                                        'class' => self::IOS_SECTION_CLASS,
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
                                        'class' => self::IOS_SECTION_CLASS,
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

    private static function renderHierarchyDiagram(Agency $agency): HtmlString
    {
        $nodes = [];
        $warnings = [];
        $structureTargets = [];

        $agencyTypeId = (int) ($agency->agency_type_id ?? 0);
        $agencyRole = $agencyTypeId === 1 ? 'Agencia master' : 'Agencia general';
        $currentAgencyCode = trim((string) ($agency->code ?? ''));

        $nodes[] = self::renderHierarchyNode(
            title: $agencyRole,
            name: self::resolveAgencyDisplayName($agency),
            subtitle: trim((string) ($agency->code ?? 'Sin código')),
            status: (string) ($agency->status ?? 'Sin estado'),
            tone: $agencyTypeId === 1 ? 'emerald' : 'amber',
            structure: self::structureSummaryForAgency($agency),
        );
        if ($currentAgencyCode !== '') {
            $structureTargets[$currentAgencyCode] = self::resolveAgencyDisplayName($agency);
        }

        if ($agencyTypeId === 1) {
            $ownerCode = strtoupper(trim((string) ($agency->owner_code ?? '')));

            if ($ownerCode === 'TDG-100' && strtoupper(trim((string) ($agency->code ?? ''))) !== 'TDG-100') {
                $nodes = [
                    self::renderHierarchyNode(
                        title: 'Casa matriz',
                        name: 'TUDRENCASA',
                        subtitle: 'TDG-100',
                        status: 'ACTIVO',
                        tone: 'blue',
                        structure: self::structureSummaryForAgencyCode('TDG-100'),
                    ),
                    ...$nodes,
                ];
                $structureTargets['TDG-100'] = 'TUDRENCASA';
            } elseif ($ownerCode !== '' && $ownerCode !== 'TDG-100') {
                $warnings[] = 'La agencia master tiene owner_code distinto a TDG-100.';
            }
        } else {
            $ownerCode = trim((string) ($agency->owner_code ?? ''));

            if ($ownerCode === '') {
                $warnings[] = 'La agencia general no tiene owner_code configurado.';
            } else {
                $masterAgency = Agency::query()
                    ->select(['code', 'name_corporative', 'agency_type_id', 'status'])
                    ->where('code', $ownerCode)
                    ->where('agency_type_id', 1)
                    ->first();

                if ($masterAgency instanceof Agency) {
                    $nodes[] = self::renderHierarchyNode(
                        title: 'Agencia master',
                        name: self::resolveAgencyDisplayName($masterAgency),
                        subtitle: trim((string) ($masterAgency->code ?? 'Sin código')),
                        status: (string) ($masterAgency->status ?? 'Sin estado'),
                        tone: 'emerald',
                        structure: self::structureSummaryForAgency($masterAgency),
                    );
                    $masterCode = trim((string) ($masterAgency->code ?? ''));
                    if ($masterCode !== '') {
                        $structureTargets[$masterCode] = self::resolveAgencyDisplayName($masterAgency);
                    }
                } else {
                    $warnings[] = 'No se encontró agencia master válida usando owner_code de esta agencia general.';
                }
            }
        }

        $diagram = '<div class="rounded-xl border border-slate-200/70 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-white/[0.03]">'
            .'<div class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">Flujo jerárquico</div>'
            .'<div class="flex flex-wrap items-stretch gap-2">'.implode(self::renderHierarchyArrow(), $nodes).'</div>';

        if (count($warnings) > 0) {
            $diagram .= '<div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">'
                .'<div class="mb-1 font-semibold">Validaciones pendientes de jerarquía</div>'
                .'<ul class="list-disc space-y-1 pl-4">';

            foreach ($warnings as $warning) {
                $diagram .= '<li>'.e($warning).'</li>';
            }

            $diagram .= '</ul></div>';
        }

        $diagram .= self::renderStructureCardsByAgency($structureTargets);

        $diagram .= '</div>';

        return new HtmlString($diagram);
    }

    private static function resolveAgencyDisplayName(Agency $agency): string
    {
        $agencyCode = strtoupper(trim((string) ($agency->code ?? '')));

        if ($agencyCode === 'TDG-100') {
            return 'TUDRENCASA';
        }

        return (string) ($agency->name_corporative ?? 'Sin razón social');
    }

    private static function renderHierarchyArrow(): string
    {
        return '<div class="flex items-center justify-center px-1 text-slate-400 dark:text-slate-500">→</div>';
    }

    private static function renderHierarchyNode(
        string $title,
        string $name,
        string $subtitle,
        string $status,
        string $tone,
        ?string $structure = null
    ): string {
        $tonePalette = match ($tone) {
            'emerald' => 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-500/30 dark:bg-emerald-500/10',
            'amber' => 'border-amber-200 bg-amber-50/80 dark:border-amber-500/30 dark:bg-amber-500/10',
            default => 'border-sky-200 bg-sky-50/80 dark:border-sky-500/30 dark:bg-sky-500/10',
        };

        $statusBadge = self::renderStatusBadge($status);

        return '<div class="min-w-[220px] flex-1 rounded-xl border p-3 '.$tonePalette.'">'
            .'<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300">'.e($title).'</div>'
            .'<div class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">'.e($name).'</div>'
            .'<div class="mt-1 text-xs text-slate-600 dark:text-slate-300">'.e($subtitle).'</div>'
            .($structure !== null ? '<div class="mt-1 text-[11px] font-medium text-slate-700 dark:text-slate-200">'.e($structure).'</div>' : '')
            .'<div class="mt-2">'.$statusBadge.'</div>'
            .'</div>';
    }

    private static function structureSummaryForAgency(Agency $agency): string
    {
        return self::structureSummaryForAgencyCode((string) ($agency->code ?? ''));
    }

    private static function structureSummaryForAgencyCode(string $agencyCode): string
    {
        $normalizedAgencyCode = trim($agencyCode);

        if ($normalizedAgencyCode === '') {
            return 'Sin estructura de agentes/subagentes';
        }

        $agentsCount = Agent::query()
            ->where('owner_code', $normalizedAgencyCode)
            ->where('agent_type_id', 2)
            ->count('*');

        $subagentsCount = Agent::query()
            ->where('owner_code', $normalizedAgencyCode)
            ->where('agent_type_id', 3)
            ->count('*');

        if ($agentsCount === 0 && $subagentsCount === 0) {
            return 'Sin estructura de agentes/subagentes';
        }

        return "Agentes: {$agentsCount} | Subagentes: {$subagentsCount}";
    }

    /**
     * @param  array<string, string>  $structureTargets
     */
    private static function renderStructureCardsByAgency(array $structureTargets): string
    {
        if ($structureTargets === []) {
            return '';
        }

        $sections = '<div class="mt-4 space-y-4">';

        foreach ($structureTargets as $agencyCode => $agencyName) {
            $agents = Agent::query()
                ->select(['id', 'name', 'status', 'email', 'phone', 'agent_type_id', 'owner_agent'])
                ->where('owner_code', $agencyCode)
                ->orderBy('agent_type_id', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            $agentsGroup = $agents->where('agent_type_id', 2)->values();
            $subagentsGroup = $agents->where('agent_type_id', 3)->values();

            $sections .= '<div class="rounded-xl border border-slate-200/70 bg-white/70 p-3 dark:border-white/10 dark:bg-white/[0.04]">'
                .'<div class="mb-2 flex flex-wrap items-center gap-2">'
                .'<span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-1 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/30">Estructura de agencia</span>'
                .'<span class="text-sm font-semibold text-slate-900 dark:text-slate-100">'.e($agencyName).'</span>'
                .'<span class="text-xs text-slate-500 dark:text-slate-300">('.e($agencyCode).')</span>'
                .'</div>'
                .self::renderAgentCardsGroup('Agentes principales', $agentsGroup)
                .self::renderAgentCardsGroup('Subagentes', $subagentsGroup)
                .'</div>';
        }

        $sections .= '</div>';

        return $sections;
    }

    private static function renderAgentCardsGroup(string $title, mixed $records): string
    {
        if (! method_exists($records, 'count') || $records->count() === 0) {
            return '<div class="mb-2 rounded-lg border border-dashed border-slate-300/80 px-3 py-2 text-xs text-slate-500 dark:border-white/20 dark:text-slate-300">'
                .e($title).': Sin registros'
                .'</div>';
        }

        $cards = '<div class="mb-2">'
            .'<div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-300">'.e($title).' ('.e((string) $records->count()).')</div>'
            .'<div class="grid grid-cols-1 gap-2 lg:grid-cols-2">';

        foreach ($records as $record) {
            $agentCode = 'AGT-000'.(int) ($record->id ?? 0);
            $statusBadge = self::renderStatusBadge((string) ($record->status ?? 'SIN ESTADO'));

            $cards .= '<div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-white/[0.03]">'
                .'<div class="flex flex-wrap items-center justify-between gap-2">'
                .'<div class="text-sm font-semibold text-slate-900 dark:text-slate-100">'.e((string) ($record->name ?? 'Sin nombre')).'</div>'
                .$statusBadge
                .'</div>'
                .'<div class="mt-1 text-xs text-slate-600 dark:text-slate-300">'.e($agentCode).'</div>'
                .'<div class="mt-2 space-y-1 text-xs text-slate-600 dark:text-slate-300">'
                .'<div>📧 '.e((string) ($record->email ?? 'Sin correo')).'</div>'
                .'<div>📞 '.e((string) ($record->phone ?? 'Sin teléfono')).'</div>';

            if ((int) ($record->agent_type_id ?? 0) === 3 && filled($record->owner_agent)) {
                $cards .= '<div>👤 Superior: AGT-000'.e((string) $record->owner_agent).'</div>';
            }

            $cards .= '</div></div>';
        }

        $cards .= '</div></div>';

        return $cards;
    }

    private static function renderStatusBadge(string $status): string
    {
        $normalizedStatus = strtoupper(trim($status));

        $statusClass = match ($normalizedStatus) {
            'ACTIVO', 'ACTIVA' => 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
            'POR REVISION', 'PENDIENTE' => 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/30',
            'INACTIVO', 'INACTIVA' => 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/20 dark:text-rose-200 dark:ring-rose-500/30',
            default => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-500/20 dark:text-slate-200 dark:ring-slate-500/30',
        };

        return '<span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-semibold ring-1 '.$statusClass.'">'
            .e($normalizedStatus !== '' ? $normalizedStatus : 'SIN ESTADO')
            .'</span>';
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

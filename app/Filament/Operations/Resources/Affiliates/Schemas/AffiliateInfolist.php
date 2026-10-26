<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Affiliates\Schemas;

use App\Filament\Operations\Support\OperationsLocationMapAction;
use App\Filament\Shared\MedicalQuestionnaires\MedicalQuestionnaireAnswersEntry;
use App\Models\Affiliate;
use App\Support\ClinicalEntitlements\OperationsAffiliatePlanBenefitsCard;
use App\Support\Filament\PatientAgeDisplay;
use App\Support\Operations\OperationsMapSearchAddress;
use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class AffiliateInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_ADDRESS_CARD = 'rounded-[1.25rem] border border-emerald-200/75 bg-gradient-to-br from-emerald-50/95 via-white to-slate-50/85 p-4 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.92),0_10px_28px_-12px_rgba(16,185,129,0.18)] ring-1 ring-emerald-300/40 dark:border-emerald-500/30 dark:from-emerald-950/35 dark:via-gray-900/90 dark:to-slate-950/90 dark:ring-emerald-400/25 sm:p-5';

    private const IOS_TABLE_WRAP_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/90 shadow-sm dark:border-white/10 dark:bg-gray-900/40 overflow-hidden';

    private static function formatAddress(?string $state): ?string
    {
        if (! filled($state)) {
            return null;
        }

        return trim((string) $state);
    }

    private static function locationSummary(Affiliate $record): ?string
    {
        return OperationsMapSearchAddress::locationLine(
            $record->country?->name,
            $record->state?->definition,
            $record->city?->definition,
            filled($record->region) ? (string) $record->region : null,
        );
    }

    private static function statusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA' => 'success',
            'PENDIENTE' => 'warning',
            'EXCLUIDO', 'INACTIVO' => 'danger',
            default => 'gray',
        };
    }

    private static function billingCollectionStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'PAGADO' => 'success',
            'POR PAGAR' => 'warning',
            'CANCELADO' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return list<\Filament\Infolists\Components\TextEntry>
     */
    private static function medicalQuestionIconEntries(): array
    {
        return [MedicalQuestionnaireAnswersEntry::make(fn (Model $record): ?Model => $record->affiliation)];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('affiliateInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos personales')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Datos personales')
                                    ->description(fn (Affiliate $record): string => ($record->full_name ?? '—').' · '.($record->phone ?? '—').' · '.($record->email ?? '—'))
                                    ->icon(Heroicon::OutlinedUser)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('full_name')
                                                    ->label('Nombre completo')
                                                    ->size('lg')
                                                    ->weight('semibold')
                                                    ->color('gray')
                                                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== '' ? mb_strtoupper($state) : '—'),
                                                TextEntry::make('status')
                                                    ->label('Estatus')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::statusColor($state))
                                                    ->icon(Heroicon::OutlinedSignal),
                                                TextEntry::make('nro_identificacion')
                                                    ->label('Identificación')
                                                    ->prefix('V-')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->copyable()
                                                    ->badge()
                                                    ->color('success'),
                                                TextEntry::make('birth_date')
                                                    ->label('Fecha de nacimiento')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->formatStateUsing(function (mixed $state): ?string {
                                                        if (blank($state)) {
                                                            return null;
                                                        }
                                                        try {
                                                            return Carbon::parse($state)->format('d/m/Y');
                                                        } catch (\Throwable) {
                                                            return (string) $state;
                                                        }
                                                    })
                                                    ->placeholder('—'),
                                                TextEntry::make('age')
                                                    ->formatStateUsing(PatientAgeDisplay::formatter('birth_date'))
                                                    ->label('Edad'),
                                                TextEntry::make('sex')
                                                    ->label('Sexo')
                                                    ->badge()
                                                    ->color('gray'),
                                                TextEntry::make('phone')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable(),
                                                TextEntry::make('email')
                                                    ->label('Correo')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->wrap(),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de registro')
                                                    ->icon(Heroicon::OutlinedClock)
                                                    ->dateTime('d/m/Y H:i'),
                                            ]),
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_ADDRESS_CARD,
                                            ])
                                            ->schema([
                                                TextEntry::make('address')
                                                    ->label('Dirección de residencia')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->iconColor('success')
                                                    ->weight('semibold')
                                                    ->size(TextSize::Medium)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->copyable()
                                                    ->copyMessage('Dirección copiada')
                                                    ->formatStateUsing(fn (?string $state): ?string => self::formatAddress($state))
                                                    ->helperText(function (Affiliate $record): ?string {
                                                        $summary = self::locationSummary($record);

                                                        return filled($summary) ? 'Ubicación: '.$summary : null;
                                                    })
                                                    ->placeholder('Sin dirección registrada')
                                                    ->suffixAction(OperationsLocationMapAction::forAffiliate()),
                                            ]),
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado / provincia')
                                                    ->icon(Heroicon::OutlinedMap)
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('country.name')
                                                    ->label('País')
                                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('region')
                                                    ->label('Región')
                                                    ->icon(Heroicon::OutlinedSquares2x2)
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Agente')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->visible(fn (Affiliate $record): bool => (bool) ($record->affiliation?->agent_id))
                            ->schema([
                                Section::make('Agente')
                                    ->description('Cuando la afiliación tiene agente asignado.')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('affiliation.agent.name')
                                                    ->label('Nombre')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('medium')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.agent.ci')
                                                    ->label('Identificación')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->copyable()
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.agent.phone')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.agent.email')
                                                    ->label('Correo')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—')
                                                    ->wrap(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Agencia')
                            ->icon(Heroicon::OutlinedBuildingLibrary)
                            ->visible(fn (Affiliate $record): bool => $record->affiliation !== null && $record->affiliation->agent_id === null)
                            ->schema([
                                Section::make('Agencia')
                                    ->description('Cuando no hay agente (canal agencia).')
                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('affiliation.agency.name_corporative')
                                                    ->label('Razón social')
                                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                                    ->weight('medium')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.agency.ci_responsable')
                                                    ->label('CI responsable')
                                                    ->copyable()
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.agency.phone')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.agency.email')
                                                    ->label('Correo')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—')
                                                    ->wrap(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Afiliación')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Section::make('Afiliación')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->description('Datos del plan y cobranzas vinculadas al código de afiliación.')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('affiliation.code')
                                                    ->label('Código')
                                                    ->icon(Heroicon::OutlinedHashtag)
                                                    ->badge()
                                                    ->color('primary'),
                                                TextEntry::make('plan.description')
                                                    ->label('Plan')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->wrap(),
                                                TextEntry::make('plan.businessUnit.definition')
                                                    ->label('Unidad de negocio')
                                                    ->badge()
                                                    ->color('gray'),
                                                TextEntry::make('affiliation.specific_business_unit')
                                                    ->label('Unidad de negocio específica')
                                                    ->placeholder('—')
                                                    ->wrap(),
                                                TextEntry::make('coverage.price')
                                                    ->label('Cobertura')
                                                    ->money('USD')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.effective_date')
                                                    ->label('Vigencia')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.service_providers')
                                                    ->label('Proveedores')
                                                    ->formatStateUsing(function (mixed $state): ?string {
                                                        if (blank($state)) {
                                                            return null;
                                                        }
                                                        if (is_array($state)) {
                                                            return implode(', ', array_filter(array_map('strval', $state)));
                                                        }

                                                        return (string) $state;
                                                    })
                                                    ->columnSpan(['default' => 1, 'lg' => 3])
                                                    ->wrap(),
                                            ]),
                                        RepeatableEntry::make('affiliation.billingCollections')
                                            ->label('Próximos pagos y estatus de cobranza')
                                            ->placeholder('No hay cobranzas registradas para este código de afiliación.')
                                            ->columnSpanFull()
                                            ->extraAttributes([
                                                'class' => self::IOS_TABLE_WRAP_CLASS,
                                            ])
                                            ->table([
                                                TableColumn::make('Vencimiento'),
                                                TableColumn::make('Estado cobranza'),
                                                TableColumn::make('Frecuencia'),
                                                TableColumn::make('Próx. pago (reg.)'),
                                                TableColumn::make('Monto'),
                                            ])
                                            ->schema([
                                                TextEntry::make('expiration_date')
                                                    ->label('Vencimiento')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estado cobranza')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::billingCollectionStatusColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('payment_frequency')
                                                    ->label('Frecuencia')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('next_payment_date')
                                                    ->label('Próx. pago (reg.)')
                                                    ->icon(Heroicon::OutlinedCalendar)
                                                    ->placeholder('—'),
                                                TextEntry::make('total_amount')
                                                    ->label('Monto')
                                                    ->money('USD')
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Aliado nivel 1')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make('Aliado de servicio nivel 1')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('affiliation.aliado_1_name')
                                                    ->label('Nombre del aliado')
                                                    ->default(fn (Affiliate $record): string => $record->affiliation?->aliado_1_name ?? '—')
                                                    ->icon(Heroicon::OutlinedDocumentText)
                                                    ->badge()
                                                    ->color('primary'),
                                                TextEntry::make('affiliation.date_init_aliado_1')
                                                    ->label('Inicio')
                                                    ->default(fn (Affiliate $record): string => $record->affiliation?->date_init_aliado_1 ?? '—')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary'),
                                                TextEntry::make('affiliation.date_end_aliado_1')
                                                    ->label('Vencimiento')
                                                    ->default(fn (Affiliate $record): string => $record->affiliation?->date_end_aliado_1 ?? '—')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary'),
                                                IconEntry::make('affiliation_voucher_aliado_1')
                                                    ->label('Voucher')
                                                    ->icon(fn (Affiliate $record): string => filled($record->affiliation?->vaucher_aliado_1)
                                                        ? 'heroicon-o-check-circle'
                                                        : 'heroicon-o-x-circle')
                                                    ->color(fn (Affiliate $record): string => filled($record->affiliation?->vaucher_aliado_1) ? 'success' : 'danger')
                                                    ->url(fn (Affiliate $record): ?string => filled($record->affiliation?->vaucher_aliado_1)
                                                        ? asset('storage/'.$record->affiliation->vaucher_aliado_1)
                                                        : null)
                                                    ->openUrlInNewTab(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Aliado ILS')
                            ->icon(Heroicon::OutlinedDocumentCheck)
                            ->schema([
                                Section::make('Aliado de servicio nivel 2 (ILS)')
                                    ->icon(Heroicon::OutlinedDocumentCheck)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('vaucherIls')
                                                    ->label('Voucher ILS')
                                                    ->default(fn (Affiliate $record): string => filled($record->vaucherIls) ? (string) $record->vaucherIls : '—')
                                                    ->icon(Heroicon::OutlinedDocumentText)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->copyable(),
                                                TextEntry::make('dateInit')
                                                    ->label('Inicio')
                                                    ->default(fn (Affiliate $record): string => filled($record->dateInit) ? (string) $record->dateInit : '—')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary'),
                                                TextEntry::make('dateEnd')
                                                    ->label('Vencimiento')
                                                    ->default(fn (Affiliate $record): string => filled($record->dateEnd) ? (string) $record->dateEnd : '—')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->badge()
                                                    ->color('primary'),
                                                IconEntry::make('document_ils_entry')
                                                    ->label('Documento ILS')
                                                    ->icon(fn (Affiliate $record): string => filled($record->document_ils)
                                                        ? 'heroicon-o-check-circle'
                                                        : 'heroicon-o-x-circle')
                                                    ->color(fn (Affiliate $record): string => filled($record->document_ils) ? 'success' : 'danger')
                                                    ->url(fn (Affiliate $record): ?string => filled($record->document_ils)
                                                        ? asset('storage/'.$record->document_ils)
                                                        : null)
                                                    ->openUrlInNewTab(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Beneficios del plan')
                            ->icon(Heroicon::OutlinedQueueList)
                            ->schema([
                                Section::make('Beneficios del plan')
                                    ->description('Uso clínico y límite comercial en una sola lista.')
                                    ->icon(Heroicon::OutlinedQueueList)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        View::make('filament.operations.affiliates.plan-benefits-clinical')
                                            ->viewData(fn (mixed $record): array => OperationsAffiliatePlanBenefitsCard::viewData(
                                                $record instanceof Affiliate ? $record : null
                                            ))
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Cuestionario médico')
                            ->icon(Heroicon::OutlinedHeart)
                            ->hidden(fn (Affiliate $record): bool => (int) $record->plan_id !== 3)
                            ->schema([
                                Section::make('Cuestionario médico')
                                    ->description('Visible para el plan configurado (ID 3).')
                                    ->icon(Heroicon::OutlinedHeart)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema(self::medicalQuestionIconEntries()),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Responsable de pago')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Section::make('Responsable de pago')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('affiliation.full_name_payer')
                                                    ->label('Nombre')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('medium')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.nro_identificacion_payer')
                                                    ->label('Identificación')
                                                    ->copyable()
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.phone_payer')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.email_payer')
                                                    ->label('Correo')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—')
                                                    ->wrap(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

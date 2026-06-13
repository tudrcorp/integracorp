<?php

declare(strict_types=1);

namespace App\Filament\Shared\RenovationHistories;

use App\Models\AffiliationRenovationHistory;
use App\Support\AffiliationAffiliateFeeCalculator;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RenovationHistoryInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('renovationHistoryInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes(['class' => self::TABS_CONTAINER])
                    ->tabs([
                        Tab::make('Aceptación')
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                Section::make('Renovación aceptada')
                                    ->description('Snapshot aplicado al expediente al confirmar la renovación.')
                                    ->icon('heroicon-o-check-badge')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(4)
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('acceptance_badge')
                                                    ->label('Resultado')
                                                    ->state(fn (): string => 'Aceptada')
                                                    ->badge()
                                                    ->color('success')
                                                    ->icon('heroicon-m-check-circle'),
                                                TextEntry::make('accepted_at')
                                                    ->label('Fecha de aceptación')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->icon('heroicon-m-calendar'),
                                                TextEntry::make('accepted_by')
                                                    ->label('Aceptada por')
                                                    ->icon('heroicon-m-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('code_affiliation')
                                                    ->label('Código afiliación')
                                                    ->icon('heroicon-m-qr-code')
                                                    ->badge()
                                                    ->color('success')
                                                    ->copyable(),
                                                TextEntry::make('affiliate.full_name')
                                                    ->label('Titular')
                                                    ->formatStateUsing(fn (?string $state): string => filled($state)
                                                        ? Str::title(Str::lower($state))
                                                        : '—')
                                                    ->icon('heroicon-m-user')
                                                    ->placeholder('—'),
                                                TextEntry::make('affiliation.nro_identificacion_ti')
                                                    ->label('Cédula de identidad')
                                                    ->icon('heroicon-m-identification')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('status_at_accept')
                                                    ->label('Estatus al aceptar')
                                                    ->badge()
                                                    ->color('warning'),
                                                TextEntry::make('remaining_days_at_accept')
                                                    ->label('Días restantes al aceptar')
                                                    ->badge()
                                                    ->color(fn (AffiliationRenovationHistory $record): string => match (true) {
                                                        ($record->remaining_days_at_accept ?? 0) < 0 => 'danger',
                                                        ($record->remaining_days_at_accept ?? 0) <= 30 => 'warning',
                                                        default => 'success',
                                                    })
                                                    ->formatStateUsing(fn (?int $state): string => $state === null
                                                        ? '—'
                                                        : ($state < 0
                                                            ? abs($state).' día(s) de retraso'
                                                            : $state.' día(s) restantes')),
                                                TextEntry::make('vigencia_resumen')
                                                    ->label('Vigencia aplicada')
                                                    ->state(fn (AffiliationRenovationHistory $record): string => ($record->previous_effective_date ?? '—')
                                                        .' → '
                                                        .$record->new_effective_date)
                                                    ->icon('heroicon-m-calendar-days')
                                                    ->weight('medium'),
                                                TextEntry::make('date_renewal')
                                                    ->label('Fecha de renovación')
                                                    ->date('d/m/Y')
                                                    ->icon('heroicon-m-calendar'),
                                                TextEntry::make('total_persons')
                                                    ->label('Personas')
                                                    ->numeric()
                                                    ->icon('heroicon-m-users'),
                                                TextEntry::make('payment_frequency')
                                                    ->label('Frecuencia de pago')
                                                    ->badge()
                                                    ->color('gray'),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Plan y montos')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Plan y cobertura')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(4)
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('plan.description')
                                                    ->label('Plan aplicado')
                                                    ->badge()
                                                    ->color(fn (AffiliationRenovationHistory $record): string => match ((int) $record->plan_id) {
                                                        AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID => 'primary',
                                                        AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID => 'warning',
                                                        default => 'gray',
                                                    }),
                                                TextEntry::make('previousPlan.description')
                                                    ->label('Plan anterior')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->visible(fn (AffiliationRenovationHistory $record): bool => filled($record->previous_plan_id))
                                                    ->placeholder('—'),
                                                TextEntry::make('coverage.description')
                                                    ->label('Cobertura')
                                                    ->placeholder('Plan inicial sin cobertura'),
                                                TextEntry::make('birth_date')
                                                    ->label('Fecha de nacimiento (titular)')
                                                    ->date('d/m/Y')
                                                    ->placeholder('—'),
                                                TextEntry::make('age')
                                                    ->label('Edad a renovación')
                                                    ->suffix(' años')
                                                    ->placeholder('—'),
                                                TextEntry::make('ageRange.range')
                                                    ->label('Rango de edad titular')
                                                    ->placeholder('—'),
                                                TextEntry::make('fee')
                                                    ->label('Tarifa titular (anual)')
                                                    ->money('USD'),
                                            ]),
                                    ]),
                                Section::make('Montos aplicados')
                                    ->icon('heroicon-o-banknotes')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(4)
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('subtotal_anual')
                                                    ->label('Subtotal anual')
                                                    ->money('USD')
                                                    ->weight('bold'),
                                                TextEntry::make('subtotal_biannual')
                                                    ->label('Subtotal semestral')
                                                    ->money('USD'),
                                                TextEntry::make('subtotal_quarterly')
                                                    ->label('Subtotal trimestral')
                                                    ->money('USD'),
                                                TextEntry::make('subtotal_monthly')
                                                    ->label('Subtotal mensual')
                                                    ->money('USD'),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Negociación')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Candidata a negociación comercial')
                                    ->description('Indica si requirió contacto por cambio de Plan Ideal a Plan Especial.')
                                    ->icon('heroicon-o-hand-raised')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(2)
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                IconEntry::make('is_negotiation_candidate')
                                                    ->label('Requiere negociación')
                                                    ->boolean()
                                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                                    ->falseIcon('heroicon-o-check-circle')
                                                    ->trueColor('warning')
                                                    ->falseColor('success'),
                                                TextEntry::make('negotiation_notes')
                                                    ->label('Notas')
                                                    ->columnSpanFull()
                                                    ->placeholder('Sin observaciones de negociación.')
                                                    ->visible(fn (AffiliationRenovationHistory $record): bool => $record->is_negotiation_candidate),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Estructura comercial')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Jerarquía comercial')
                                    ->icon('heroicon-o-building-office')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(4)
                                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('affiliation.agency.name_corporative')
                                                    ->label('Agencia')
                                                    ->icon('heroicon-m-building-office-2')
                                                    ->placeholder('—'),
                                                TextEntry::make('code_agency')
                                                    ->label('Código agencia')
                                                    ->badge()
                                                    ->color('gray'),
                                                TextEntry::make('affiliation.agent.name')
                                                    ->label('Agente')
                                                    ->icon('heroicon-m-academic-cap')
                                                    ->placeholder('—'),
                                                TextEntry::make('agent_id')
                                                    ->label('Código agente')
                                                    ->badge()
                                                    ->color('gray'),
                                                TextEntry::make('owner_code')
                                                    ->label('Código jerarquía')
                                                    ->placeholder('—'),
                                                TextEntry::make('owner_agent')
                                                    ->label('Agente jerarquía')
                                                    ->placeholder('—'),
                                                TextEntry::make('source_renovation_id')
                                                    ->label('Renovación origen (ID)')
                                                    ->placeholder('—'),
                                                TextEntry::make('created_at')
                                                    ->label('Registrado')
                                                    ->dateTime('d/m/Y H:i'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}

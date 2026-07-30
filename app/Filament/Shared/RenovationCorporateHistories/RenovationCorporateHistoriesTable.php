<?php

declare(strict_types=1);

namespace App\Filament\Shared\RenovationCorporateHistories;

use App\Models\AffiliationCorporateRenovationHistory;
use App\Support\AffiliationAffiliateFeeCalculator;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RenovationCorporateHistoriesTable
{
    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas')
                ->icon(Heroicon::OutlinedQueueList),
            'negociacion' => Tab::make('Negociación Plan Especial')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_negotiation_candidate', true)),
            'recientes' => Tab::make('Últimos 30 días')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('accepted_at', '>=', now()->subDays(30))),
        ];
    }

    /**
     * @param  class-string<resource>  $historyResourceClass
     * @param  class-string<resource>  $affiliationResourceClass
     */
    public static function configure(
        Table $table,
        string $historyResourceClass,
        string $affiliationResourceClass,
    ): Table {
        return $table
            ->heading('Histórico de renovaciones corporativas')
            ->description('Renovaciones aceptadas y aplicadas al expediente. Cada registro es un snapshot inmutable al momento de la aceptación.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'affiliationCorporate.agency',
                'affiliationCorporate.agent',
                'affiliateCorporate',
                'plan',
                'previousPlan',
                'coverage',
            ]))
            ->defaultSort('accepted_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(1)
            ->recordTitleAttribute('code_affiliation')
            ->emptyStateHeading('Sin renovaciones aceptadas')
            ->emptyStateDescription('Cuando se acepte una renovación corporativa, el registro aparecerá aquí con la vigencia y montos aplicados.')
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox)
            ->columns([
                TextColumn::make('code_affiliation')
                    ->label('Código')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->weight('semibold')
                    ->color('success')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->action(
                        ViewAction::make('viewFromCode')
                            ->url(fn (AffiliationCorporateRenovationHistory $record): string => $historyResourceClass::getUrl('view', ['record' => $record])),
                    ),
                TextColumn::make('affiliationCorporate.name_corporate')
                    ->label('Empresa')
                    ->icon(Heroicon::OutlinedUser)
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Str::title(Str::lower($state))
                        : '—')
                    ->searchable()
                    ->sortable()
                    ->limit(28)
                    ->tooltip(fn (AffiliationCorporateRenovationHistory $record): ?string => filled($record->affiliationCorporate?->name_corporate)
                        ? (string) $record->affiliationCorporate->name_corporate
                        : null),
                TextColumn::make('accepted_at')
                    ->label('Aceptación')
                    ->dateTime('d/m/Y H:i')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->sortable()
                    ->description(fn (AffiliationCorporateRenovationHistory $record): string => $record->accepted_by ?? '—'),
                TextColumn::make('vigencia_resumen')
                    ->label('Vigencia')
                    ->state(fn (AffiliationCorporateRenovationHistory $record): string => ($record->previous_effective_date ?? '—')
                        .' → '
                        .$record->new_effective_date)
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('new_effective_date', $direction))
                    ->description(fn (AffiliationCorporateRenovationHistory $record): ?string => $record->date_renewal
                        ? 'Renovación desde '.$record->date_renewal->format('d/m/Y')
                        : null),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color(fn (AffiliationCorporateRenovationHistory $record): string => self::planBadgeColor((int) $record->plan_id))
                    ->sortable()
                    ->description(fn (AffiliationCorporateRenovationHistory $record): ?string => filled($record->coverage?->description)
                        ? 'Cobertura: '.$record->coverage->description
                        : 'Plan inicial sin cobertura'),
                TextColumn::make('subtotal_anual')
                    ->label('Tarifa anual')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
                TextColumn::make('total_persons')
                    ->label('Pers.')
                    ->numeric()
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->sortable(),
                IconColumn::make('is_negotiation_candidate')
                    ->label('Neg.')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->falseIcon(Heroicon::CheckCircle)
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->alignCenter()
                    ->tooltip(fn (AffiliationCorporateRenovationHistory $record): ?string => $record->is_negotiation_candidate
                        ? ($record->negotiation_notes ?? 'Plan Especial')
                        : 'Sin negociación'),
                TextColumn::make('affiliationCorporate.agency.name_corporative')
                    ->label('Agencia')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('affiliationCorporate.agent.name')
                    ->label('Agente')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status_at_accept')
                    ->label('Estatus al aceptar')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_negotiation_candidate')
                    ->label('Negociación Plan Especial')
                    ->placeholder('Todas')
                    ->trueLabel('Solo negociación')
                    ->falseLabel('Sin negociación'),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->options([
                        AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID => 'Plan Inicial',
                        AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID => 'Plan Ideal',
                        AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID => 'Plan Especial',
                    ]),
                Filter::make('accepted_at')
                    ->label('Fecha de aceptación')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('accepted_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('accepted_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    private static function planBadgeColor(int $planId): string
    {
        return match ($planId) {
            AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID => 'primary',
            AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID => 'warning',
            AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID => 'gray',
            default => 'gray',
        };
    }
}

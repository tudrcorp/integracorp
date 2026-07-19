<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Support\AffiliationAffiliateFeeCalculator;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

final class AffiliationRenovationHistoryInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function tab(string $iosSectionClass = self::IOS_SECTION_CLASS): Tab
    {
        return Tab::make('Historial de renovaciones')
            ->icon(Heroicon::OutlinedArrowPath)
            ->badge(function ($record): ?string {
                $count = (int) ($record->renovation_histories_count
                    ?? ($record->relationLoaded('renovationHistories')
                        ? $record->renovationHistories->count()
                        : 0));

                return $count > 0 ? (string) $count : null;
            })
            ->schema([
                Section::make('Renovaciones aceptadas')
                    ->description('Cada registro confirma que el cliente aceptó la renovación. Los montos son el snapshot aplicado al expediente.')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->extraAttributes(['class' => $iosSectionClass])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                            ->schema([
                                RepeatableEntry::make('renovationHistories')
                                    ->label('')
                                    ->placeholder('Aún no hay renovaciones aceptadas para esta afiliación.')
                                    ->table([
                                        TableColumn::make('Aceptación')->width('14%'),
                                        TableColumn::make('Vigencia')->width('20%'),
                                        TableColumn::make('Plan')->width('16%'),
                                        TableColumn::make('Titular')->width('18%'),
                                        TableColumn::make('Tarifa anual')->width('12%')->alignEnd(),
                                        TableColumn::make('Pers.')->width('6%')->alignCenter(),
                                        TableColumn::make('Negociación')->width('14%')->alignCenter(),
                                    ])
                                    ->schema([
                                        TextEntry::make('acceptance_badge')
                                            ->label('Aceptación')
                                            ->state(fn (): string => 'Aceptada')
                                            ->badge()
                                            ->color('success')
                                            ->icon(Heroicon::CheckCircle)
                                            ->iconColor('success')
                                            ->weight('semibold')
                                            ->helperText(fn ($record): string => ($record->accepted_at?->format('d/m/Y H:i') ?? '—')
                                                .' · '.($record->accepted_by ?? '—')),
                                        TextEntry::make('vigencia_resumen')
                                            ->label('Vigencia')
                                            ->state(fn ($record): string => ($record->previous_effective_date ?? '—')
                                                .' → '
                                                .$record->new_effective_date)
                                            ->icon(Heroicon::OutlinedCalendarDays)
                                            ->iconColor('info')
                                            ->weight('medium')
                                            ->helperText(fn ($record): ?string => $record->date_renewal
                                                ? 'Renovación desde '.$record->date_renewal->format('d/m/Y')
                                                : null),
                                        TextEntry::make('plan.description')
                                            ->label('Plan')
                                            ->placeholder('—')
                                            ->badge()
                                            ->color(fn ($record): string => self::planBadgeColor((int) $record->plan_id))
                                            ->helperText(fn ($record): ?string => filled($record->coverage?->description)
                                                ? 'Cobertura: '.$record->coverage->description
                                                : 'Plan inicial sin cobertura'),
                                        TextEntry::make('affiliate.full_name')
                                            ->label('Titular')
                                            ->formatStateUsing(fn (?string $state): string => filled($state)
                                                ? Str::title(Str::lower($state))
                                                : '—')
                                            ->icon(Heroicon::OutlinedUser)
                                            ->weight('medium')
                                            ->helperText(fn ($record): ?string => $record->age !== null
                                                ? $record->age.' años a la renovación'
                                                : null),
                                        TextEntry::make('subtotal_anual')
                                            ->label('Tarifa anual')
                                            ->money('USD')
                                            ->alignEnd()
                                            ->weight('bold')
                                            ->color('success'),
                                        TextEntry::make('total_persons')
                                            ->label('Pers.')
                                            ->alignCenter()
                                            ->badge()
                                            ->color('info'),
                                        IconEntry::make('is_negotiation_candidate')
                                            ->label('Negociación')
                                            ->boolean()
                                            ->trueIcon(Heroicon::OutlinedChatBubbleLeftRight)
                                            ->falseIcon(Heroicon::CheckCircle)
                                            ->trueColor('warning')
                                            ->falseColor('success')
                                            ->alignCenter()
                                            ->helperText(fn ($record): ?string => $record->is_negotiation_candidate
                                                ? ($record->negotiation_notes ?? 'Plan Especial')
                                                : null),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
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

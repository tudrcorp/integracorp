<?php

declare(strict_types=1);

namespace App\Filament\Shared\Renovations;

use App\Models\Renovation;
use App\Support\AffiliationAffiliateFeeCalculator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Js;

class RenovationsTable
{
    private const COLUMN_GROUP_HEADER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-100/95 [&_th]:via-slate-50/90 [&_th]:to-transparent dark:[&_th]:from-white/[0.08] dark:[&_th]:via-white/[0.04] dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-200/80 dark:[&_th]:border-white/10';

    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todas')
                ->icon(Heroicon::OutlinedQueueList),
            'periodo' => Tab::make('Período de renovación')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'PERIODO DE RENOVACION')),
            'vigente' => Tab::make('Vigentes')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'VIGENTE')),
            'retraso' => Tab::make('Con retraso')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('remaining_days', '<', 0)),
            'negociacion' => Tab::make('Negociación Plan Especial')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_negotiation_candidate', true)),
        ];
    }

    /**
     * @param  class-string<resource>  $renovationResourceClass
     * @param  class-string<resource>  $affiliationResourceClass
     */
    public static function configure(
        Table $table,
        string $renovationResourceClass,
        string $affiliationResourceClass,
    ): Table {
        return $table
            ->heading('Renovaciones individuales')
            ->description('Priorice filas en rojo (retraso) y ámbar (período de renovación o negociación). Use las pestañas y filtros para enfocar la gestión comercial.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'affiliation',
                'plan',
                'previousPlan',
                'coverage',
                'ageRange',
            ]))
            ->defaultSort('remaining_days', 'asc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->recordTitleAttribute('code_affiliation')
            ->emptyStateHeading('Sin renovaciones registradas')
            ->emptyStateDescription('El proceso diario genera registros para afiliaciones ACTIVA con fecha de vigencia. Si acaba de ejecutarse, espere la próxima corrida o contacte a sistemas.')
            ->emptyStateIcon(Heroicon::OutlinedArrowPath)
            ->columns([
                ColumnGroup::make('Afiliación y vencimiento', [
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
                                ->url(fn (Renovation $record): string => $renovationResourceClass::getUrl('view', ['record' => $record])),
                        ),
                    TextColumn::make('affiliation.full_name_ti')
                        ->label('Titular')
                        ->icon(Heroicon::OutlinedUser)
                        ->searchable()
                        ->sortable()
                        ->limit(28)
                        ->tooltip(fn (Renovation $record): ?string => filled($record->affiliation?->full_name_ti)
                            ? (string) $record->affiliation->full_name_ti
                            : null)
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('remaining_days')
                        ->label('Días')
                        ->alignCenter()
                        ->sortable()
                        ->icon(fn (Renovation $record): Heroicon => self::remainingDaysIcon($record))
                        ->iconColor(fn (Renovation $record): string => self::remainingDaysColor($record))
                        ->badge()
                        ->color(fn (Renovation $record): string => self::remainingDaysColor($record))
                        ->state(fn (Renovation $record): string => self::remainingDaysLabel($record))
                        ->description(fn (Renovation $record): string => self::remainingDaysDescription($record)),
                    TextColumn::make('status')
                        ->label('Estatus')
                        ->icon(fn (?string $state): Heroicon => match ($state) {
                            'PERIODO DE RENOVACION' => Heroicon::OutlinedClock,
                            'VIGENTE' => Heroicon::OutlinedShieldCheck,
                            default => Heroicon::OutlinedMinusCircle,
                        })
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'PERIODO DE RENOVACION' => 'warning',
                            'VIGENTE' => 'success',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'PERIODO DE RENOVACION' => 'En renovación',
                            'VIGENTE' => 'Vigente',
                            default => (string) ($state ?? '—'),
                        })
                        ->sortable(),
                    TextColumn::make('date_renewal')
                        ->label('Vence')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->date('d/m/Y')
                        ->description(fn (Renovation $record): ?string => $record->date_renewal?->diffForHumans())
                        ->sortable(),
                    TextColumn::make('birth_date')
                        ->label('Nacimiento')
                        ->icon(Heroicon::OutlinedCake)
                        ->date('d/m/Y')
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('age')
                        ->label('Edad')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->numeric()
                        ->alignCenter()
                        ->suffix(' años')
                        ->placeholder('—')
                        ->sortable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Plan y montos', [
                    TextColumn::make('negotiation_flag')
                        ->label('Negociación')
                        ->alignCenter()
                        ->state(fn (Renovation $record): string => $record->is_negotiation_candidate
                            ? 'Plan Especial'
                            : '—')
                        ->icon(fn (Renovation $record): ?Heroicon => $record->is_negotiation_candidate
                            ? Heroicon::OutlinedChatBubbleLeftRight
                            : null)
                        ->badge()
                        ->color(fn (Renovation $record): string => $record->is_negotiation_candidate ? 'warning' : 'gray')
                        ->placeholder('—')
                        ->extraAttributes(fn (Renovation $record): array => $record->is_negotiation_candidate && filled($record->negotiation_notes)
                            ? [
                                'x-tooltip' => '{ content: '.Js::from($record->negotiation_notes).', theme: $store.theme, delay: [400, 0], maxWidth: 420 }',
                            ]
                            : [])
                        ->toggleable(),
                    TextColumn::make('plan.description')
                        ->label('Plan')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->badge()
                        ->color(fn (Renovation $record): string => self::planBadgeColor($record))
                        ->description(fn (Renovation $record): ?string => filled($record->previous_plan_id)
                            ? 'Antes: '.($record->previousPlan?->description ?? 'Plan #'.$record->previous_plan_id)
                            : null)
                        ->placeholder('—')
                        ->sortable(query: fn ($query, string $direction) => $query->orderBy('plan_id', $direction)),
                    TextColumn::make('coverage.price')
                        ->label('Cobertura')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->formatStateUsing(fn ($state, Renovation $record): string => $record->coverage_id
                            ? 'US$ '.number_format((float) ($state ?? 0), 2)
                            : 'Inicial')
                        ->alignEnd()
                        ->toggleable(),
                    TextColumn::make('subtotal_anual')
                        ->label('Anual')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->money('USD')
                        ->weight('medium')
                        ->alignEnd()
                        ->sortable(),
                    TextColumn::make('total_persons')
                        ->label('Pers.')
                        ->icon(Heroicon::OutlinedUsers)
                        ->numeric()
                        ->alignCenter()
                        ->sortable(),
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia')
                        ->badge()
                        ->color('gray')
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Estructura comercial', [
                    TextColumn::make('code_agency')
                        ->label('Agencia')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->placeholder('—'),
                    TextColumn::make('agent_id')
                        ->label('Agente')
                        ->icon(Heroicon::OutlinedAcademicCap)
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable()
                        ->placeholder('—'),
                    TextColumn::make('owner_code')
                        ->label('Jerarquía')
                        ->icon(Heroicon::OutlinedLink)
                        ->badge()
                        ->color('success')
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('updated_at')
                        ->label('Actualizado')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (Renovation $record): string => $record->updated_at->diffForHumans())
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
            ])
            ->recordClasses(fn (Renovation $record): array => self::recordRowClasses($record))
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus renovación')
                    ->options([
                        'VIGENTE' => 'Vigente',
                        'PERIODO DE RENOVACION' => 'Período de renovación',
                    ])
                    ->placeholder('Todos')
                    ->native(false),
                TernaryFilter::make('is_negotiation_candidate')
                    ->label('Negociación Plan Especial')
                    ->placeholder('Todas')
                    ->trueLabel('Solo candidatas')
                    ->falseLabel('Sin candidatura')
                    ->native(false),
                SelectFilter::make('plan_id')
                    ->label('Plan proyectado')
                    ->relationship('plan', 'description')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('urgencia')
                    ->label('Urgencia')
                    ->form([
                        \Filament\Forms\Components\Select::make('nivel')
                            ->label('Nivel')
                            ->options([
                                'retraso' => 'Con retraso (días negativos)',
                                'periodo' => 'Período de renovación (≤ 30 días)',
                                'vigente' => 'Vigente (> 30 días)',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            ($data['nivel'] ?? null) === 'retraso',
                            fn (Builder $q): Builder => $q->where('remaining_days', '<', 0),
                        )->when(
                            ($data['nivel'] ?? null) === 'periodo',
                            fn (Builder $q): Builder => $q->where('remaining_days', '<=', 30),
                        )->when(
                            ($data['nivel'] ?? null) === 'vigente',
                            fn (Builder $q): Builder => $q->where('remaining_days', '>', 30),
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['nivel'] ?? null) {
                            'retraso' => 'Urgencia: retraso',
                            'periodo' => 'Urgencia: período de renovación',
                            'vigente' => 'Urgencia: vigente',
                            default => null,
                        };
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver renovación')
                        ->icon(Heroicon::OutlinedEye)
                        ->url(fn (Renovation $record): string => $renovationResourceClass::getUrl('view', ['record' => $record])),
                    Action::make('viewAffiliation')
                        ->label('Ver afiliación')
                        ->icon(Heroicon::OutlinedUserGroup)
                        ->color('info')
                        ->url(fn (Renovation $record): string => $affiliationResourceClass::getUrl('view', ['record' => $record->affiliation_id]))
                        ->visible(fn (Renovation $record): bool => $record->affiliation_id > 0),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->button(),
            ]);
    }

    private static function remainingDaysColor(Renovation $record): string
    {
        if ($record->remaining_days < 0) {
            return 'danger';
        }

        if ($record->remaining_days <= 30) {
            return 'warning';
        }

        return 'success';
    }

    private static function remainingDaysIcon(Renovation $record): Heroicon
    {
        if ($record->remaining_days < 0) {
            return Heroicon::OutlinedExclamationTriangle;
        }

        if ($record->remaining_days <= 30) {
            return Heroicon::OutlinedClock;
        }

        return Heroicon::OutlinedCalendarDays;
    }

    private static function remainingDaysLabel(Renovation $record): string
    {
        if ($record->remaining_days === null) {
            return '—';
        }

        if ($record->remaining_days < 0) {
            return (string) $record->remaining_days;
        }

        return (string) $record->remaining_days;
    }

    private static function remainingDaysDescription(Renovation $record): string
    {
        if ($record->remaining_days === null) {
            return 'Sin conteo';
        }

        if ($record->remaining_days < 0) {
            $days = abs($record->remaining_days);

            return $days === 1 ? '1 día de retraso' : "{$days} días de retraso";
        }

        if ($record->remaining_days === 0) {
            return 'Vence hoy';
        }

        if ($record->remaining_days === 1) {
            return '1 día restante';
        }

        return "{$record->remaining_days} días restantes";
    }

    private static function planBadgeColor(Renovation $record): string
    {
        return match ((int) $record->plan_id) {
            AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID => 'primary',
            AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID => 'warning',
            AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID => 'gray',
            default => 'gray',
        };
    }

    /**
     * @return list<string>
     */
    private static function recordRowClasses(Renovation $record): array
    {
        if ($record->is_negotiation_candidate && $record->remaining_days < 0) {
            return ['bg-red-50/90 dark:bg-red-950/25 border-l-4 border-red-500'];
        }

        if ($record->is_negotiation_candidate) {
            return ['bg-amber-50/90 dark:bg-amber-950/25 border-l-4 border-amber-500'];
        }

        if ($record->remaining_days < 0) {
            return ['bg-red-50/80 dark:bg-red-950/20 border-l-4 border-red-500'];
        }

        if ($record->remaining_days <= 30) {
            return ['bg-amber-50/70 dark:bg-amber-950/20 border-l-4 border-amber-400'];
        }

        return ['border-l-4 border-transparent'];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Shared\Renovations;

use App\Models\Renovation;
use App\Services\AcceptAffiliationRenovationsService;
use App\Services\ManualRenovationAcceptanceOptions;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Filament\Renovations\AcceptRenovationActionForm;
use App\Support\FilamentDateDisplay;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;

class RenovationsTable
{
    private const COLUMN_GROUP_HEADER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-100/95 [&_th]:via-slate-50/90 [&_th]:to-transparent dark:[&_th]:from-white/[0.08] dark:[&_th]:via-white/[0.04] dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-200/80 dark:[&_th]:border-white/10';

    private const COLUMN_GROUP_BEFORE_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-200/90 [&_th]:via-slate-100/95 [&_th]:to-slate-50/50 dark:[&_th]:from-slate-800/80 dark:[&_th]:via-slate-900/60 dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-300/80 dark:[&_th]:border-white/10';

    private const COLUMN_GROUP_AFTER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-emerald-100/95 [&_th]:via-emerald-50/90 [&_th]:to-transparent dark:[&_th]:from-emerald-950/50 dark:[&_th]:via-emerald-900/30 dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-emerald-900 dark:[&_th]:text-emerald-100 [&_th]:border-b [&_th]:border-emerald-200/80 dark:[&_th]:border-emerald-800/40';

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
            ->description('Compare el expediente vigente (columnas grises) con lo que quedará si acepta la renovación (columnas verdes). Priorice filas en rojo (retraso) y ámbar (período o negociación).')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'affiliation.agency',
                'affiliation.agent',
                'affiliation.plan',
                'affiliation.coverage',
                'plan',
                'previousPlan',
                'coverage',
                'ageRange',
            ]))
            ->defaultSort('remaining_days', 'asc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(1)
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
                    TextColumn::make('affiliation.nro_identificacion_ti')
                        ->label('Cédula')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->searchable()
                        ->sortable()
                        ->copyable()
                        ->copyMessage('Cédula copiada')
                        ->placeholder('—'),
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
                        ->label('Vence renovación')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->date('d/m/Y')
                        ->description(fn (Renovation $record): ?string => $record->date_renewal?->diffForHumans())
                        ->sortable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Expediente vigente (antes)', [
                    TextColumn::make('affiliation.effective_date')
                        ->label('Vigencia desde')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->state(fn (Renovation $record): string => FilamentDateDisplay::toDmy($record->affiliation?->effective_date) ?? '—')
                        ->description('Expediente actual')
                        ->placeholder('—'),
                    TextColumn::make('affiliation.plan.description')
                        ->label('Plan actual')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->badge()
                        ->color(fn (Renovation $record): string => self::planBadgeColorForPlanId(
                            (int) ($record->affiliation?->plan_id ?? 0),
                        ))
                        ->placeholder('—'),
                    TextColumn::make('current_coverage_summary')
                        ->label('Cobertura actual')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->state(fn (Renovation $record): string => self::coverageLabel(
                            $record->affiliation?->coverage_id,
                            $record->affiliation?->coverage?->price,
                        ))
                        ->alignEnd(),
                    TextColumn::make('affiliation.fee_anual')
                        ->label('Anual vigente')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->money('USD')
                        ->alignEnd()
                        ->weight('medium')
                        ->description(fn (Renovation $record): ?string => filled($record->affiliation?->total_amount)
                            ? 'Pago: US$ '.number_format((float) $record->affiliation->total_amount, 2)
                            : null),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_BEFORE_CLASS]),
                ColumnGroup::make('Si acepta renovación', [
                    TextColumn::make('projected_effective_date')
                        ->label('Nueva vigencia')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->state(fn (Renovation $record): string => $record->date_renewal?->format('d/m/Y') ?? '—')
                        ->description('Desde fecha de renovación')
                        ->color('success'),
                    TextColumn::make('plan.description')
                        ->label('Plan proyectado')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->badge()
                        ->color(fn (Renovation $record): string => self::planBadgeColor($record))
                        ->description(fn (Renovation $record): ?string => self::projectedPlanDescription($record))
                        ->placeholder('—')
                        ->sortable(query: fn ($query, string $direction) => $query->orderBy('plan_id', $direction)),
                    TextColumn::make('coverage.price')
                        ->label('Cobertura proyectada')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->state(fn (Renovation $record): string => self::coverageLabel(
                            $record->coverage_id,
                            $record->coverage?->price,
                        ))
                        ->alignEnd(),
                    TextColumn::make('subtotal_anual')
                        ->label('Anual proyectado')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->money('USD')
                        ->weight('bold')
                        ->alignEnd()
                        ->color(fn (Renovation $record): string => self::annualDeltaColor($record))
                        ->description(fn (Renovation $record): string => 'Titular: US$ '.number_format((float) $record->fee, 2))
                        ->sortable(),
                    TextColumn::make('age')
                        ->label('Edad a renovar')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->numeric()
                        ->alignCenter()
                        ->suffix(' años')
                        ->description(fn (Renovation $record): ?string => $record->birth_date
                            ? 'Nac. '.$record->birth_date->format('d/m/Y')
                            : null)
                        ->placeholder('—')
                        ->sortable(),
                    TextColumn::make('total_persons')
                        ->label('Pers.')
                        ->icon(Heroicon::OutlinedUsers)
                        ->numeric()
                        ->alignCenter()
                        ->sortable(),
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
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_AFTER_CLASS]),
                ColumnGroup::make('Variación', [
                    TextColumn::make('renewal_delta_summary')
                        ->label('Cambio')
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->state(fn (Renovation $record): string => self::annualDeltaLabel($record))
                        ->description(fn (Renovation $record): string => self::renewalChangeDescription($record))
                        ->badge()
                        ->color(fn (Renovation $record): string => self::annualDeltaColor($record))
                        ->alignCenter(),
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia')
                        ->badge()
                        ->color('gray')
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Estructura comercial', [
                    TextColumn::make('affiliation.agency.name_corporative')
                        ->label('Agencia')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->searchable()
                        ->sortable()
                        ->limit(32)
                        ->description(fn (Renovation $record): ?string => filled($record->code_agency)
                            ? (string) $record->code_agency
                            : null)
                        ->tooltip(fn (Renovation $record): ?string => filled($record->affiliation?->agency?->name_corporative)
                            ? (string) $record->affiliation->agency->name_corporative
                            : null)
                        ->placeholder('—'),
                    TextColumn::make('affiliation.agent.name')
                        ->label('Agente')
                        ->icon(Heroicon::OutlinedAcademicCap)
                        ->searchable()
                        ->sortable()
                        ->limit(28)
                        ->description(fn (Renovation $record): ?string => filled($record->agent_id)
                            ? (string) $record->agent_id
                            : null)
                        ->tooltip(fn (Renovation $record): ?string => filled($record->affiliation?->agent?->name)
                            ? (string) $record->affiliation->agent->name
                            : null)
                        ->placeholder('—'),
                    TextColumn::make('code_agency')
                        ->label('Cód. agencia')
                        ->icon(Heroicon::OutlinedHashtag)
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->placeholder('—'),
                    TextColumn::make('agent_id')
                        ->label('Cód. agente')
                        ->icon(Heroicon::OutlinedHashtag)
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true)
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
                Filter::make('remaining_days_range')
                    ->label('Días restantes')
                    ->form([
                        Select::make('preset')
                            ->label('Rango rápido')
                            ->options([
                                'retraso' => 'Con retraso (menor a 0)',
                                'hoy' => 'Vence hoy (0 días)',
                                '1_7' => 'De 1 a 7 días',
                                '8_30' => 'De 8 a 30 días',
                                '31_60' => 'De 31 a 60 días',
                                '61_90' => 'De 61 a 90 días',
                                'mas_90' => 'Más de 90 días',
                            ])
                            ->placeholder('Personalizar con mínimo/máximo')
                            ->native(false)
                            ->live(),
                        TextInput::make('min')
                            ->label('Mínimo de días')
                            ->numeric()
                            ->integer()
                            ->placeholder('Ej: -15')
                            ->helperText('Valores negativos = días de retraso.')
                            ->disabled(fn (callable $get): bool => filled($get('preset'))),
                        TextInput::make('max')
                            ->label('Máximo de días')
                            ->numeric()
                            ->integer()
                            ->placeholder('Ej: 30')
                            ->disabled(fn (callable $get): bool => filled($get('preset'))),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        [$min, $max] = self::resolveRemainingDaysRange($data);

                        return $query
                            ->when($min !== null, fn (Builder $q): Builder => $q->where('remaining_days', '>=', $min))
                            ->when($max !== null, fn (Builder $q): Builder => $q->where('remaining_days', '<=', $max));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        [$min, $max] = self::resolveRemainingDaysRange($data);

                        if ($min === null && $max === null) {
                            return null;
                        }

                        if ($min !== null && $max !== null) {
                            return "Días restantes: {$min} a {$max}";
                        }

                        if ($min !== null) {
                            return "Días restantes ≥ {$min}";
                        }

                        return "Días restantes ≤ {$max}";
                    }),
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
                    self::acceptRenovationAction(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::acceptRenovationsBulkAction(),
                ]),
            ]);
    }

    private static function acceptRenovationAction(): Action
    {
        return self::configureAcceptModal(
            Action::make('acceptRenovation')
                ->label('Aceptar renovación')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->modalHeading('Aceptar renovación')
                ->modalDescription('Confirme la propuesta del sistema o ajuste manualmente las condiciones comerciales acordadas con el cliente.')
                ->modalSubmitActionLabel('Confirmar aceptación')
                ->visible(fn (Renovation $record): bool => $record->status === 'PERIODO DE RENOVACION')
                ->fillForm(fn (Renovation $record): array => [
                    'plan_id' => $record->plan_id,
                    'age_range_id' => $record->age_range_id,
                    'coverage_id' => $record->coverage_id,
                    'payment_frequency' => $record->payment_frequency ?? 'ANUAL',
                ])
                ->form(fn (Renovation $record): array => AcceptRenovationActionForm::schema(collect([$record])))
                ->action(function (Renovation $record, array $data): void {
                    self::processAcceptRenovations(collect([$record]), $data);
                }),
        );
    }

    private static function acceptRenovationsBulkAction(): BulkAction
    {
        return self::configureAcceptModal(
            BulkAction::make('acceptRenovations')
                ->label('Aceptar renovaciones')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->modalHeading('Aceptar renovaciones seleccionadas')
                ->modalDescription('Confirme la propuesta del sistema o ajuste manualmente plan, cobertura, rango de edad y frecuencia para las filas elegidas.')
                ->modalSubmitActionLabel('Confirmar aceptación')
                ->deselectRecordsAfterCompletion()
                ->fillForm(function (Collection $records): array {
                    /** @var Renovation|null $reference */
                    $reference = $records->first();

                    return [
                        'plan_id' => $reference?->plan_id,
                        'age_range_id' => $reference?->age_range_id,
                        'coverage_id' => $reference?->coverage_id,
                        'payment_frequency' => $reference?->payment_frequency ?? 'ANUAL',
                    ];
                })
                ->form(fn (Collection $records): array => AcceptRenovationActionForm::schema($records))
                ->action(function (Collection $records, array $data): void {
                    self::processAcceptRenovations($records, $data);
                }),
        );
    }

    /**
     * @template T of Action|BulkAction
     *
     * @param  T  $action
     * @return T
     */
    private static function configureAcceptModal(Action|BulkAction $action): Action|BulkAction
    {
        return $action
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->modalIconColor('success')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false);
    }

    /**
     * @param  Collection<int, Renovation>  $records
     * @param  array<string, mixed>  $data
     */
    private static function processAcceptRenovations(Collection $records, array $data = []): void
    {
        $acceptedBy = Auth::user()?->name ?? 'SISTEMA';

        try {
            $manualOptions = ManualRenovationAcceptanceOptions::fromFormData($data);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->danger()
                ->title('Configuración incompleta')
                ->body($exception->getMessage())
                ->send();

            return;
        }

        $result = app(AcceptAffiliationRenovationsService::class)->accept($records, $acceptedBy, $manualOptions);

        if ($result->accepted > 0) {
            Notification::make()
                ->success()
                ->title($result->accepted === 1 ? 'Renovación aceptada' : 'Renovaciones aceptadas')
                ->body("Se aplicaron {$result->accepted} renovación(es) al expediente y se registraron en el historial.")
                ->send();
        }

        if ($result->skipped > 0) {
            $detail = $result->messages !== []
                ? implode("\n", array_slice($result->messages, 0, 5))
                : 'Revise el estatus de las filas seleccionadas.';

            Notification::make()
                ->warning()
                ->title('Algunas renovaciones no se procesaron')
                ->body("Omitidas: {$result->skipped}. {$detail}")
                ->persistent()
                ->send();
        }

        if ($result->accepted === 0 && $result->skipped === 0) {
            Notification::make()
                ->info()
                ->title('Sin cambios')
                ->body('No se seleccionaron renovaciones para procesar.')
                ->send();
        }
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int|null, 1: int|null}
     */
    private static function resolveRemainingDaysRange(array $data): array
    {
        $preset = $data['preset'] ?? null;

        if (filled($preset)) {
            return match ($preset) {
                'retraso' => [null, -1],
                'hoy' => [0, 0],
                '1_7' => [1, 7],
                '8_30' => [8, 30],
                '31_60' => [31, 60],
                '61_90' => [61, 90],
                'mas_90' => [91, null],
                default => [null, null],
            };
        }

        $min = filled($data['min'] ?? null) ? (int) $data['min'] : null;
        $max = filled($data['max'] ?? null) ? (int) $data['max'] : null;

        if ($min !== null && $max !== null && $min > $max) {
            return [$max, $min];
        }

        return [$min, $max];
    }

    private static function planBadgeColor(Renovation $record): string
    {
        return self::planBadgeColorForPlanId((int) $record->plan_id);
    }

    private static function planBadgeColorForPlanId(int $planId): string
    {
        return match ($planId) {
            AffiliationAffiliateFeeCalculator::IDEAL_PLAN_ID => 'primary',
            AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID => 'warning',
            AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID => 'gray',
            default => 'gray',
        };
    }

    private static function coverageLabel(?int $coverageId, mixed $price): string
    {
        if (! filled($coverageId)) {
            return 'Inicial';
        }

        return 'US$ '.number_format((float) ($price ?? 0), 2);
    }

    private static function projectedPlanDescription(Renovation $record): ?string
    {
        if ($record->is_negotiation_candidate) {
            return 'Cambio a Plan Especial';
        }

        if ((int) ($record->affiliation?->plan_id ?? 0) !== (int) $record->plan_id) {
            return 'Antes: '.($record->affiliation?->plan?->description ?? '—');
        }

        return 'Mismo plan';
    }

    private static function annualDeltaLabel(Renovation $record): string
    {
        $current = (float) ($record->affiliation?->fee_anual ?? 0);
        $projected = (float) $record->subtotal_anual;
        $delta = round($projected - $current, 2);

        if ($delta === 0.0) {
            return 'Sin cambio';
        }

        $sign = $delta > 0 ? '+' : '';

        return $sign.'US$ '.number_format(abs($delta), 2);
    }

    private static function annualDeltaColor(Renovation $record): string
    {
        $current = (float) ($record->affiliation?->fee_anual ?? 0);
        $projected = (float) $record->subtotal_anual;
        $delta = round($projected - $current, 2);

        if ($delta === 0.0) {
            return 'gray';
        }

        return $delta > 0 ? 'warning' : 'success';
    }

    private static function renewalChangeDescription(Renovation $record): string
    {
        $parts = [];

        $currentPlan = (string) ($record->affiliation?->plan?->description ?? '—');
        $projectedPlan = (string) ($record->plan?->description ?? '—');

        if ($currentPlan !== $projectedPlan) {
            $parts[] = "Plan: {$currentPlan} → {$projectedPlan}";
        }

        $currentVigencia = FilamentDateDisplay::toDmy($record->affiliation?->effective_date) ?? '—';
        $newVigencia = $record->date_renewal?->format('d/m/Y') ?? '—';

        if ($currentVigencia !== $newVigencia) {
            $parts[] = "Vigencia: {$currentVigencia} → {$newVigencia}";
        }

        if ($parts === []) {
            return 'Tarifas recalculadas; plan y vigencia iguales';
        }

        return implode(' · ', $parts);
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

<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\DroppedAffiliationCollections\Tables;

use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Models\Collection;
use App\Models\CollectionDropoutReview;
use App\Models\User;
use App\Support\Collections\CollectionDueDate;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Collections\DroppedAffiliationCollections;
use App\Support\Filament\SummaryCards;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Bandeja de cuotas de afiliaciones dadas de baja. Una fila por cuota: por decidir,
 * mantenida como deuda o anulada (mientras se pueda revertir).
 */
class DroppedAffiliationCollectionsTable
{
    /**
     * Fecha de baja por código de afiliación, calculada una vez por página de la tabla.
     *
     * @var array<int, array<string, CarbonImmutable>>
     */
    private static array $dropoutDatesByPage = [];

    /**
     * Estatus de baja agrupados como los ve el analista.
     *
     * @var array<string, list<string>>
     */
    private const STATUS_GROUPS = [
        'EXCLUIDA' => ['EXCLUIDO', 'EXCLUIDA'],
        'INACTIVA' => ['INACTIVO', 'INACTIVA'],
        'ANULADA' => ['ANULADO', 'ANULADA'],
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Cuotas de afiliaciones excluidas, inactivas o anuladas')
            ->description('Una fila por cuota. Las que vencen después de la baja se anulan solas al dar de baja la afiliación; aquí quedan las que hay que decidir. Seleccione cuotas y elija «Anular» o «Mantener como deuda».')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([...CollectionReceivableReport::eagerLoads(), 'activeDropoutReview']))
            ->defaultSort('filter_next_payment_date', 'asc')
            ->searchPlaceholder('Buscar por afiliación, afiliado, cédula o aviso')
            ->searchDebounce('350ms')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('affiliation_code')
                    ->label('Afiliación')
                    ->weight('semibold')
                    ->searchable()
                    ->copyable()
                    ->description(fn (Collection $record): string => DroppedAffiliationCollections::affiliationType($record) === DroppedAffiliationCollections::TYPE_CORPORATE ? 'Corporativa' : 'Individual'),
                TextColumn::make('affiliation_status')
                    ->label('Estatus actual')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Collection $record): ?string => CollectionReceivableReport::affiliateStatus($record)),
                TextColumn::make('dropped_at')
                    ->label('Fecha de baja')
                    ->placeholder('Sin registro')
                    ->tooltip('Fecha en que se registró la baja en la bitácora de la afiliación.')
                    ->state(fn (Collection $record, HasTable $livewire): ?string => self::dropoutDate($record, $livewire)?->format('d/m/Y')),
                TextColumn::make('affiliate_full_name')
                    ->label('Afiliado o titular')
                    ->wrap()
                    ->searchable(['affiliate_full_name', 'affiliate_ci_rif'])
                    ->description(fn (Collection $record): string => (string) ($record->affiliate_ci_rif ?? '')),
                TextColumn::make('collection_invoice_number')
                    ->label('Aviso')
                    ->searchable()
                    ->placeholder('—')
                    ->description(fn (Collection $record): ?string => CollectionReceivableReport::installmentLabel($record)),
                TextColumn::make('filter_next_payment_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (Collection $record): string => self::daysLabel($record)),
                TextColumn::make('timing')
                    ->label('¿Vence antes o después de la baja?')
                    ->badge()
                    ->state(fn (Collection $record, HasTable $livewire): string => DroppedAffiliationCollections::timingLabel(CollectionDueDate::of($record), self::dropoutDate($record, $livewire)))
                    ->color(fn (string $state): string => match ($state) {
                        'Antes de la baja' => 'warning',
                        'Después de la baja' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('total_amount')
                    ->label('Monto')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('review')
                    ->label('Revisión')
                    ->badge()
                    ->state(fn (Collection $record): string => DroppedAffiliationCollections::reviewLabel($record))
                    ->color(fn (Collection $record): string => match ($record->activeDropoutReview?->decision) {
                        CollectionDropoutReview::DECISION_KEPT => 'info',
                        CollectionDropoutReview::DECISION_CANCELLED, CollectionDropoutReview::DECISION_AUTO_CANCELLED => 'danger',
                        default => 'warning',
                    })
                    ->tooltip(fn (Collection $record): ?string => ($review = $record->activeDropoutReview) instanceof CollectionDropoutReview
                        ? $review->decided_by_name.' · '.$review->created_at?->format('d/m/Y H:i').' — '.$review->note
                        : null),
            ])
            ->filters([
                SelectFilter::make('review')
                    ->label('Revisión')
                    ->options(DroppedAffiliationCollections::reviewOptions())
                    ->default(DroppedAffiliationCollections::REVIEW_UNDECIDED)
                    ->placeholder('Todas')
                    ->query(fn (Builder $query, array $data): Builder => DroppedAffiliationCollections::applyReviewFilter($query, $data['value'] ?? null)),
                SelectFilter::make('collection_status')
                    ->label('Vencimiento')
                    ->placeholder('Vencidas y por vencer')
                    ->options([
                        CollectionReceivableReport::STATUS_OVERDUE => 'Vencidas',
                        CollectionReceivableReport::STATUS_PENDING => 'Por vencer',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => CollectionReceivableReport::applyCollectionStatus($query, $data['value'] ?? null)),
                SelectFilter::make('affiliation_status')
                    ->label('Estatus de la afiliación')
                    ->placeholder('Excluidas, inactivas y anuladas')
                    ->options([
                        'EXCLUIDA' => 'Excluida',
                        'INACTIVA' => 'Inactiva',
                        'ANULADA' => 'Anulada',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::applyStatusGroup($query, $data['value'] ?? null)),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->recordActions([
                Action::make('revert')
                    ->label('Revertir decisión')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (Collection $record): bool => $record->activeDropoutReview instanceof CollectionDropoutReview && self::canManage())
                    ->modalHeading('Revertir la decisión')
                    ->modalDescription(fn (Collection $record): string => self::revertDescription($record))
                    ->modalSubmitActionLabel('Revertir')
                    ->schema([self::noteField('Motivo de la reversión', 'Ej.: el cliente sí reconoce la deuda; se anuló por error.')])
                    ->action(function (Collection $record, array $data, Action $action): void {
                        self::run($action, 'No se revirtió la decisión', fn (): string => DroppedAffiliationCollections::revert([(int) $record->getKey()], $data['note'] ?? null, Auth::user()) === 1
                            ? 'Decisión revertida. La cuota volvió a «Por decidir».'
                            : 'No hubo cambios.');
                    }),
                Action::make('history')
                    ->label('Ver en Gestión de Cobranza')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Collection $record): string => CollectionResource::getUrl('index', ['search' => (string) ($record->collection_invoice_number ?: $record->affiliation_code)]), shouldOpenInNewTab: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::cancelBulkAction(),
                    self::keepBulkAction(),
                    self::revertBulkAction(),
                ])->label('Decidir'),
            ]);
    }

    public static function cancelBulkAction(): BulkAction
    {
        return BulkAction::make('cancel_dropped')
            ->label('Anular cuotas')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => self::canManage())
            ->modalHeading('Anular cuotas de afiliaciones dadas de baja')
            ->modalDescription(fn (EloquentCollection $records): string => self::selectionText($records)
                .' Pasarán a «ANULADO»: dejan de ser deuda y no se cobran. Cada una queda en su historial con esta nota y se puede revertir. '
                .'Solo se aceptan cuotas por pagar; máximo '.DroppedAffiliationCollections::MAX_BATCH.' por vez. Si alguna no cumple, no se anula ninguna.')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalSubmitActionLabel('Anular')
            ->schema([self::noteField('Motivo de la anulación', 'Ej.: afiliación excluida en mayo; Administración confirmó que estas cuotas no se cobran.')])
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection $records, array $data, BulkAction $action): void {
                self::run($action, 'No se anuló ninguna cuota', function () use ($records, $data): string {
                    $result = DroppedAffiliationCollections::cancel($records->modelKeys(), $data['note'] ?? null, Auth::user());

                    return SummaryCards::count($result['count'], 'cuota anulada', 'cuotas anuladas').' por '.SummaryCards::money($result['amount']).'.';
                });
            });
    }

    public static function keepBulkAction(): BulkAction
    {
        return BulkAction::make('keep_dropped')
            ->label('Mantener como deuda')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('info')
            ->visible(fn (): bool => self::canManage())
            ->modalHeading('Mantener cuotas como deuda')
            ->modalDescription(fn (EloquentCollection $records): string => self::selectionText($records)
                .' Seguirán «POR PAGAR» y saldrán de «Por decidir». Mientras la afiliación siga dada de baja se consultan aquí, con el filtro «Revisión: Mantenidas como deuda».')
            ->modalSubmitActionLabel('Mantener como deuda')
            ->schema([self::noteField('Motivo', 'Ej.: deuda anterior a la baja; el cliente se comprometió a pagarla.')])
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection $records, array $data, BulkAction $action): void {
                self::run($action, 'No se aplicó ningún cambio', function () use ($records, $data): string {
                    $result = DroppedAffiliationCollections::keep($records->modelKeys(), $data['note'] ?? null, Auth::user());

                    return SummaryCards::count($result['count'], 'cuota mantenida', 'cuotas mantenidas').' como deuda por '.SummaryCards::money($result['amount']).'.';
                });
            });
    }

    public static function revertBulkAction(): BulkAction
    {
        return BulkAction::make('revert_dropped')
            ->label('Revertir decisión')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (): bool => self::canManage())
            ->modalHeading('Revertir decisiones')
            ->modalDescription(fn (EloquentCollection $records): string => self::selectionText($records)
                .' Una anulada vuelve a «POR PAGAR»; una mantenida como deuda vuelve a «Por decidir». Si alguna cambió después de la decisión, no se revierte ninguna.')
            ->modalSubmitActionLabel('Revertir')
            ->schema([self::noteField('Motivo de la reversión', 'Ej.: se anularon por error; el cliente reconoce la deuda.')])
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection $records, array $data, BulkAction $action): void {
                self::run($action, 'No se revirtió ninguna cuota', function () use ($records, $data): string {
                    $count = DroppedAffiliationCollections::revert($records->modelKeys(), $data['note'] ?? null, Auth::user());

                    return SummaryCards::count($count, 'decisión revertida', 'decisiones revertidas').'.';
                });
            });
    }

    /**
     * @param  Builder<Collection>  $query
     * @return Builder<Collection>
     */
    public static function applyStatusGroup(Builder $query, ?string $group): Builder
    {
        $statuses = self::STATUS_GROUPS[$group] ?? null;

        if ($statuses === null) {
            return $query;
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $matches = fn (Builder $affiliation): Builder => $affiliation->whereRaw("UPPER(TRIM(status)) in ({$placeholders})", $statuses);

        return $query->where(function (Builder $query) use ($matches, $placeholders, $statuses): void {
            $query->whereHas('affiliationByCode', $matches)
                ->orWhereHas('affiliationCorporateByCode', $matches)
                ->orWhere(fn (Builder $orphan): Builder => $orphan
                    ->whereDoesntHave('affiliationByCode')
                    ->whereDoesntHave('affiliationCorporateByCode')
                    ->whereRaw("UPPER(TRIM(collections.affiliate_status)) in ({$placeholders})", $statuses));
        });
    }

    private static function canManage(): bool
    {
        $user = Auth::user();

        return DroppedAffiliationCollections::userCan($user instanceof User ? $user : null);
    }

    private static function noteField(string $label, string $placeholder): Textarea
    {
        return Textarea::make('note')
            ->label($label)
            ->placeholder($placeholder)
            ->helperText('Obligatoria. Queda en el historial de cada cuota y en la auditoría.')
            ->required()
            ->minLength(DroppedAffiliationCollections::NOTE_MIN_LENGTH)
            ->maxLength(2000)
            ->rows(3);
    }

    /**
     * Resumen exacto de la selección para que el analista confirme sabiendo qué toca.
     */
    private static function selectionText(EloquentCollection $records): string
    {
        $summary = DroppedAffiliationCollections::selectionSummary($records);

        return 'Seleccionó '.SummaryCards::count($summary['count'], 'cuota', 'cuotas')
            .' de '.SummaryCards::count($summary['affiliations'], 'afiliación', 'afiliaciones')
            .' por '.SummaryCards::money($summary['amount'])
            .($summary['overdue_count'] > 0
                ? ' ('.SummaryCards::count($summary['overdue_count'], 'vencida', 'vencidas').' por '.SummaryCards::money($summary['overdue_amount']).')'
                : '')
            .'.';
    }

    private static function revertDescription(Collection $record): string
    {
        $review = $record->activeDropoutReview;

        if (! $review instanceof CollectionDropoutReview) {
            return 'La cuota no tiene una decisión vigente.';
        }

        $blocker = DroppedAffiliationCollections::revertBlocker($record);
        $what = $review->isCancellation()
            ? 'La cuota volverá a «POR PAGAR» y a «Por decidir».'
            : 'La cuota volverá a «Por decidir». Sigue «POR PAGAR».';

        return 'Decisión vigente: '.$review->decisionLabel().' ('.$review->decided_by_name.', '.$review->created_at?->format('d/m/Y H:i').'). '
            .$what.($blocker === null ? '' : ' No se puede revertir: '.$blocker.'.');
    }

    /**
     * @param  callable(): string  $operation
     */
    private static function run(Action $action, string $failureTitle, callable $operation): void
    {
        try {
            $message = $operation();
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title($failureTitle)
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $action->halt();

            return;
        }

        Notification::make()
            ->title('Listo')
            ->body($message)
            ->success()
            ->send();
    }

    private static function daysLabel(Collection $record): string
    {
        $due = CollectionDueDate::of($record);
        $today = CarbonImmutable::today();

        if ($due === null) {
            return 'Sin fecha';
        }

        if ($due->lessThan($today)) {
            $days = (int) $due->diffInDays($today);

            return 'Vencida hace '.$days.' '.($days === 1 ? 'día' : 'días');
        }

        return 'Por vencer';
    }

    private static function dropoutDate(Collection $record, HasTable $livewire): ?CarbonImmutable
    {
        $records = $livewire->getTableRecords();
        $key = spl_object_id($records);

        if (! isset(self::$dropoutDatesByPage[$key])) {
            self::$dropoutDatesByPage = [
                $key => DroppedAffiliationCollections::dropoutDates(
                    collect($records instanceof AbstractPaginator || $records instanceof AbstractCursorPaginator ? $records->items() : $records)
                        ->pluck('affiliation_code')
                        ->all(),
                ),
            ];
        }

        return self::$dropoutDatesByPage[$key][(string) $record->affiliation_code] ?? null;
    }
}

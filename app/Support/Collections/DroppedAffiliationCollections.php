<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CollectionDropoutReview;
use App\Models\StatusLogAffiliation;
use App\Models\StatusLogAffiliationCorporate;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cuotas pendientes de afiliaciones dadas de baja (excluidas, inactivas o anuladas).
 *
 * - Al dar de baja una afiliación se anulan solas las cuotas por pagar que vencen desde
 *   ese día en adelante. Las ya vencidas quedan para que Administración decida: pueden
 *   ser deuda real anterior a la baja.
 * - Si la afiliación se reactiva, vuelven a «POR PAGAR» solo las cuotas que anuló la baja
 *   y que nadie tocó después.
 * - El analista anula o mantiene como deuda las que quedaron, con nota, y puede revertir.
 *
 * Cada cambio de estado deja su fila en `collection_adjustments` (historial de la cuota),
 * su decisión en `collection_dropout_reviews` y su registro en SecurityAudit. Los
 * mensajes de las excepciones están pensados para mostrarse tal cual al usuario.
 */
final class DroppedAffiliationCollections
{
    public const NOTE_MIN_LENGTH = 15;

    public const MAX_BATCH = 200;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_CORPORATE = 'corporativa';

    public const REVIEW_UNDECIDED = 'por_decidir';

    public const SYSTEM_ACTOR = 'SISTEMA';

    private static int $suppressed = 0;

    public static function isDroppedStatus(mixed $status): bool
    {
        return in_array(Str::upper(trim((string) $status)), CollectionReceivableReport::EXCLUDED_AFFILIATION_STATUSES, true);
    }

    public static function userCan(?User $user): bool
    {
        return $user instanceof User
            && UserNavigationAccess::canPerformModuleAction($user, 'ADMINISTRACION', BusinessFilamentActionPermissionRegistry::MANAGE_DROPPED_AFFILIATION_COLLECTIONS);
    }

    /**
     * Cuotas cuya afiliación está dada de baja según su estatus **actual**. Es el
     * complemento exacto de {@see CollectionReceivableReport::excludeNonCollectableAffiliations()}:
     * toda cuota está en Gestión de Cobranza o aquí, nunca en las dos.
     *
     * @param  Builder<Collection>  $query
     * @return Builder<Collection>
     */
    public static function scopeDropped(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $statuses = CollectionReceivableReport::EXCLUDED_AFFILIATION_STATUSES;
        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $dropped = fn (Builder $affiliation): Builder => $affiliation->whereRaw("UPPER(TRIM(status)) in ({$placeholders})", $statuses);

        return $query->where(function (Builder $query) use ($dropped, $table, $placeholders, $statuses): void {
            $query->whereHas('affiliationByCode', $dropped)
                ->orWhereHas('affiliationCorporateByCode', $dropped)
                ->orWhere(function (Builder $orphan) use ($table, $placeholders, $statuses): void {
                    $orphan->whereDoesntHave('affiliationByCode')
                        ->whereDoesntHave('affiliationCorporateByCode')
                        ->whereRaw("UPPER(TRIM({$table}.affiliate_status)) in ({$placeholders})", $statuses);
                });
        });
    }

    /**
     * Lo que ve la página: cuotas por pagar de afiliaciones dadas de baja (por decidir o
     * mantenidas como deuda) y las anuladas por esta vía que todavía se pueden revertir.
     *
     * @return Builder<Collection>
     */
    public static function reviewQuery(): Builder
    {
        return self::scopeDropped(Collection::query())
            ->where(function (Builder $query): void {
                $query->where('collections.status', CollectionAdjuster::STATUS_PENDING)
                    ->orWhere(function (Builder $cancelled): void {
                        $cancelled->where('collections.status', CollectionAdjuster::STATUS_CANCELLED)
                            ->whereHas('dropoutReviews', fn (Builder $review): Builder => $review
                                ->whereNull('reverted_at')
                                ->whereIn('decision', [CollectionDropoutReview::DECISION_AUTO_CANCELLED, CollectionDropoutReview::DECISION_CANCELLED]));
                    });
            });
    }

    /**
     * Cuotas por pagar de afiliaciones dadas de baja (lo que el reporte lista).
     *
     * @return Builder<Collection>
     */
    public static function pendingQuery(): Builder
    {
        return self::scopeDropped(Collection::query())->where('collections.status', CollectionAdjuster::STATUS_PENDING);
    }

    /**
     * @param  Builder<Collection>  $query
     * @return Builder<Collection>
     */
    public static function applyReviewFilter(Builder $query, ?string $review): Builder
    {
        $active = fn (array $decisions): \Closure => fn (Builder $relation): Builder => $relation->whereNull('reverted_at')->whereIn('decision', $decisions);

        return match ($review) {
            self::REVIEW_UNDECIDED => $query->where('collections.status', CollectionAdjuster::STATUS_PENDING)
                ->whereDoesntHave('dropoutReviews', fn (Builder $relation): Builder => $relation->whereNull('reverted_at')),
            CollectionDropoutReview::DECISION_KEPT => $query->where('collections.status', CollectionAdjuster::STATUS_PENDING)
                ->whereHas('dropoutReviews', $active([CollectionDropoutReview::DECISION_KEPT])),
            CollectionDropoutReview::DECISION_CANCELLED => $query->where('collections.status', CollectionAdjuster::STATUS_CANCELLED)
                ->whereHas('dropoutReviews', $active([CollectionDropoutReview::DECISION_CANCELLED, CollectionDropoutReview::DECISION_AUTO_CANCELLED])),
            default => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function reviewOptions(): array
    {
        return [
            self::REVIEW_UNDECIDED => 'Por decidir',
            CollectionDropoutReview::DECISION_KEPT => 'Mantenidas como deuda',
            CollectionDropoutReview::DECISION_CANCELLED => 'Anuladas (se pueden revertir)',
        ];
    }

    public static function reviewLabel(Collection $collection): string
    {
        $review = $collection->activeDropoutReview;

        if (! $review instanceof CollectionDropoutReview) {
            return 'Por decidir';
        }

        return $review->decisionLabel();
    }

    /**
     * Desactiva la anulación automática mientras corre el callback (la unificación de
     * grupo familiar ya resuelve las cuotas de las pólizas que excluye, con su propia
     * foto de restauración).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutAutomaticCancellation(callable $callback): mixed
    {
        self::$suppressed++;

        try {
            return $callback();
        } finally {
            self::$suppressed--;
        }
    }

    /**
     * Reacciona al cambio de estatus de una afiliación individual o corporativa.
     */
    public static function handleStatusChange(Model $affiliation): void
    {
        if (self::$suppressed > 0 || ! $affiliation->wasChanged('status')) {
            return;
        }

        $wasDropped = self::isDroppedStatus($affiliation->getRawOriginal('status'));
        $isDropped = self::isDroppedStatus($affiliation->getAttribute('status'));

        if (! $wasDropped && $isDropped) {
            self::cancelOnDropout($affiliation);
        } elseif ($wasDropped && ! $isDropped) {
            self::restoreOnReactivation($affiliation);
        }
    }

    /**
     * Anula las cuotas por pagar que vencen desde hoy. Devuelve cuántas anuló.
     */
    public static function cancelOnDropout(Model $affiliation, ?CarbonImmutable $today = null): int
    {
        $code = trim((string) $affiliation->getAttribute('code'));

        if ($code === '') {
            return 0;
        }

        $today ??= CarbonImmutable::today();
        $status = Str::upper(trim((string) $affiliation->getAttribute('status')));
        $actor = self::currentUser();
        $batch = (string) Str::uuid();
        $note = 'Anulada automáticamente: la afiliación pasó a '.$status.' el '.$today->format('d/m/Y').' y la cuota vence después de la baja.';

        return DB::transaction(function () use ($affiliation, $code, $today, $status, $actor, $batch, $note): int {
            $collections = Collection::query()
                ->where('affiliation_code', $code)
                ->where('status', CollectionAdjuster::STATUS_PENDING)
                ->whereDate('filter_next_payment_date', '>=', $today->toDateString())
                // Lo que un analista ya decidió mantener como deuda no se anula solo.
                ->whereDoesntHave('dropoutReviews', fn (Builder $review): Builder => $review
                    ->whereNull('reverted_at')
                    ->where('decision', CollectionDropoutReview::DECISION_KEPT))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($collections as $collection) {
                self::closeActiveReviews($collection, $actor, 'Reemplazada por la anulación automática de la baja.');
                $adjustment = self::changeStatus($collection, CollectionAdjuster::STATUS_CANCELLED, $note, $actor, $batch);
                self::recordReview($collection, $affiliation, CollectionDropoutReview::DECISION_AUTO_CANCELLED, CollectionAdjuster::STATUS_PENDING, CollectionAdjuster::STATUS_CANCELLED, $note, $actor, $batch, $adjustment, $status);
            }

            if ($collections->isNotEmpty()) {
                SecurityAudit::log('AUDIT_COLLECTIONS_CANCELLED_BY_DROPOUT', 'collections.dropout.auto-cancel', [
                    'affiliation_code' => $code,
                    'affiliation_status' => $status,
                    'batch_uuid' => $batch,
                    'collection_ids' => $collections->modelKeys(),
                    'amount' => round((float) $collections->sum('total_amount'), 2),
                ], $actor);
            }

            return $collections->count();
        });
    }

    /**
     * Devuelve a «POR PAGAR» las cuotas que anuló la baja, si nadie las cambió después.
     */
    public static function restoreOnReactivation(Model $affiliation): int
    {
        $code = trim((string) $affiliation->getAttribute('code'));

        if ($code === '') {
            return 0;
        }

        $actor = self::currentUser();
        $batch = (string) Str::uuid();
        $note = 'Restituida: la afiliación se reactivó ('.Str::upper(trim((string) $affiliation->getAttribute('status'))).').';

        return DB::transaction(function () use ($code, $actor, $batch, $note): int {
            $reviews = CollectionDropoutReview::query()
                ->active()
                ->where('affiliation_code', $code)
                ->where('decision', CollectionDropoutReview::DECISION_AUTO_CANCELLED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $restored = 0;

            foreach ($reviews as $review) {
                $collection = Collection::query()->whereKey($review->collection_id)->lockForUpdate()->first();

                if (! $collection instanceof Collection || self::restoreBlocker($collection, $review) !== null) {
                    continue;
                }

                $adjustment = self::changeStatus($collection, (string) ($review->status_before ?: CollectionAdjuster::STATUS_PENDING), $note, $actor, $batch);
                self::closeReview($review, $actor, $note.' Ajuste #'.$adjustment->getKey().'.');
                $restored++;
            }

            if ($restored > 0) {
                SecurityAudit::log('AUDIT_COLLECTIONS_RESTORED_BY_REACTIVATION', 'collections.dropout.restore', [
                    'affiliation_code' => $code,
                    'batch_uuid' => $batch,
                    'restored' => $restored,
                ], $actor);
            }

            return $restored;
        });
    }

    /**
     * Resumen exacto de una selección, para mostrarlo antes de confirmar.
     *
     * @param  iterable<Collection>  $collections
     * @return array{count: int, amount: float, overdue_count: int, overdue_amount: float, affiliations: int}
     */
    public static function selectionSummary(iterable $collections, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $summary = ['count' => 0, 'amount' => 0.0, 'overdue_count' => 0, 'overdue_amount' => 0.0, 'affiliations' => 0];
        $codes = [];

        foreach ($collections as $collection) {
            $amount = (float) ($collection->total_amount ?? 0);
            $summary['count']++;
            $summary['amount'] += $amount;
            $codes[(string) $collection->affiliation_code] = true;

            $due = CollectionDueDate::of($collection);

            if ($due !== null && $due->lessThan($today)) {
                $summary['overdue_count']++;
                $summary['overdue_amount'] += $amount;
            }
        }

        $summary['amount'] = round($summary['amount'], 2);
        $summary['overdue_amount'] = round($summary['overdue_amount'], 2);
        $summary['affiliations'] = count($codes);

        return $summary;
    }

    /**
     * Anula cuotas por pagar de afiliaciones dadas de baja. Todo o nada.
     *
     * @param  list<int>  $collectionIds
     * @return array{count: int, amount: float, batch_uuid: string}
     */
    public static function cancel(array $collectionIds, mixed $note, ?User $actor): array
    {
        return self::decide($collectionIds, $note, $actor, CollectionDropoutReview::DECISION_CANCELLED);
    }

    /**
     * Marca cuotas como deuda que se mantiene: siguen «POR PAGAR» y salen de «Por decidir».
     *
     * @param  list<int>  $collectionIds
     * @return array{count: int, amount: float, batch_uuid: string}
     */
    public static function keep(array $collectionIds, mixed $note, ?User $actor): array
    {
        return self::decide($collectionIds, $note, $actor, CollectionDropoutReview::DECISION_KEPT);
    }

    /**
     * Motivo por el que no se puede revertir la decisión vigente de la cuota, o null.
     */
    public static function revertBlocker(Collection $collection): ?string
    {
        $review = $collection->activeDropoutReview()->first();

        if (! $review instanceof CollectionDropoutReview) {
            return 'La cuota no tiene una decisión vigente que revertir.';
        }

        return $review->isCancellation() ? self::restoreBlocker($collection, $review) : null;
    }

    /**
     * Revierte la decisión vigente: una anulación vuelve a «POR PAGAR»; «mantener como
     * deuda» vuelve a «Por decidir». Todo o nada.
     *
     * @param  list<int>  $collectionIds
     */
    public static function revert(array $collectionIds, mixed $note, ?User $actor): int
    {
        self::assertCan($actor);
        $note = self::validNote($note);
        $ids = self::validIds($collectionIds);
        $batch = (string) Str::uuid();

        return DB::transaction(function () use ($ids, $note, $actor, $batch): int {
            $collections = Collection::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            self::assertAllExist($collections, $ids);
            $problems = [];
            $plans = [];

            foreach ($collections as $collection) {
                $review = CollectionDropoutReview::query()->active()->where('collection_id', $collection->id)->latest('id')->lockForUpdate()->first();
                $label = self::invoiceLabel($collection);

                if (! $review instanceof CollectionDropoutReview) {
                    $problems[] = "{$label} no tiene una decisión vigente";

                    continue;
                }

                if ($review->isCancellation() && ($blocker = self::restoreBlocker($collection, $review)) !== null) {
                    $problems[] = "{$label}: {$blocker}";

                    continue;
                }

                $plans[] = [$collection, $review];
            }

            if ($problems !== []) {
                throw new InvalidArgumentException('No se revirtió ninguna cuota: '.implode('; ', $problems).'.');
            }

            foreach ($plans as [$collection, $review]) {
                $closing = 'Revertida: '.$note;

                if ($review->isCancellation()) {
                    $adjustment = self::changeStatus($collection, (string) ($review->status_before ?: CollectionAdjuster::STATUS_PENDING), 'Reversión de anulación por baja: '.$note, $actor, $batch);
                    $closing .= ' Ajuste #'.$adjustment->getKey().'.';
                }

                self::closeReview($review, $actor, $closing);
            }

            SecurityAudit::log('AUDIT_DROPPED_COLLECTIONS_REVERTED', 'administration.dropped-collections.revert', [
                'panel' => 'administration',
                'batch_uuid' => $batch,
                'collection_ids' => $ids,
                'note' => $note,
            ], $actor);

            return count($plans);
        });
    }

    /**
     * Fecha en que se registró la baja de cada afiliación (la más reciente), según la
     * bitácora de estatus y las anulaciones automáticas. Una consulta por tipo.
     *
     * @param  list<string>  $codes
     * @return array<string, CarbonImmutable>
     */
    public static function dropoutDates(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(static fn (mixed $code): string => trim((string) $code), $codes))));

        if ($codes === []) {
            return [];
        }

        $dates = [];
        $keep = static function (string $code, mixed $value) use (&$dates): void {
            if (blank($value)) {
                return;
            }

            $date = CarbonImmutable::parse((string) $value);

            if (! isset($dates[$code]) || $date->greaterThan($dates[$code])) {
                $dates[$code] = $date;
            }
        };
        $dropAction = static function (Builder $query): void {
            $query->where('action', 'like', '%EXCLU%')
                ->orWhere('action', 'like', '%INACTIV%')
                ->orWhere('action', 'like', '%ANULAD%');
        };

        $individual = Affiliation::query()->whereIn('code', $codes)->pluck('code', 'id');

        if ($individual->isNotEmpty()) {
            StatusLogAffiliation::query()
                ->whereIn('affiliation_id', $individual->keys())
                ->where($dropAction)
                ->selectRaw('affiliation_id, MAX(created_at) as dropped_at')
                ->groupBy('affiliation_id')
                ->toBase()
                ->get()
                ->each(fn (object $row) => $keep((string) $individual[$row->affiliation_id], $row->dropped_at));
        }

        $corporate = AffiliationCorporate::query()->whereIn('code', $codes)->pluck('code', 'id');

        if ($corporate->isNotEmpty()) {
            StatusLogAffiliationCorporate::query()
                ->whereIn('affiliation_corporate_id', $corporate->keys())
                ->where($dropAction)
                ->selectRaw('affiliation_corporate_id, MAX(created_at) as dropped_at')
                ->groupBy('affiliation_corporate_id')
                ->toBase()
                ->get()
                ->each(fn (object $row) => $keep((string) $corporate[$row->affiliation_corporate_id], $row->dropped_at));
        }

        CollectionDropoutReview::query()
            ->whereIn('affiliation_code', $codes)
            ->where('decision', CollectionDropoutReview::DECISION_AUTO_CANCELLED)
            ->selectRaw('affiliation_code, MAX(created_at) as dropped_at')
            ->groupBy('affiliation_code')
            ->toBase()
            ->get()
            ->each(fn (object $row) => $keep((string) $row->affiliation_code, $row->dropped_at));

        return $dates;
    }

    /**
     * «Antes de la baja», «Después de la baja» o «Sin fecha de baja registrada».
     */
    public static function timingLabel(?CarbonImmutable $dueDate, ?CarbonImmutable $droppedAt): string
    {
        if ($droppedAt === null) {
            return 'Sin fecha de baja registrada';
        }

        if ($dueDate === null) {
            return 'Sin fecha de vencimiento';
        }

        return $dueDate->lessThan($droppedAt->startOfDay()) ? 'Antes de la baja' : 'Después de la baja';
    }

    public static function affiliationType(Collection $collection): string
    {
        return CollectionReceivableReport::isCorporate($collection) ? self::TYPE_CORPORATE : self::TYPE_INDIVIDUAL;
    }

    /**
     * @param  list<int>  $collectionIds
     * @return array{count: int, amount: float, batch_uuid: string}
     */
    private static function decide(array $collectionIds, mixed $note, ?User $actor, string $decision): array
    {
        self::assertCan($actor);
        $note = self::validNote($note);
        $ids = self::validIds($collectionIds);
        $batch = (string) Str::uuid();

        return DB::transaction(function () use ($ids, $note, $actor, $decision, $batch): array {
            $collections = Collection::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            self::assertAllExist($collections, $ids);

            $droppedIds = self::scopeDropped(Collection::query())->whereKey($ids)->pluck('collections.id')->map(intval(...))->all();
            $problems = [];

            foreach ($collections as $collection) {
                $label = self::invoiceLabel($collection);
                $active = CollectionDropoutReview::query()->active()->where('collection_id', $collection->id)->latest('id')->first();

                if (Str::upper(trim((string) $collection->status)) !== CollectionAdjuster::STATUS_PENDING) {
                    $problems[] = "{$label} ya no está por pagar";
                } elseif (! in_array((int) $collection->id, $droppedIds, true)) {
                    $problems[] = "{$label} es de una afiliación que ya no está dada de baja";
                } elseif ($decision === CollectionDropoutReview::DECISION_KEPT && $active?->decision === CollectionDropoutReview::DECISION_KEPT) {
                    $problems[] = "{$label} ya está mantenida como deuda";
                }
            }

            if ($problems !== []) {
                throw new InvalidArgumentException('No se aplicó ningún cambio: '.implode('; ', $problems).'. Quítelas de la selección o recargue la tabla.');
            }

            $affiliations = self::affiliationsFor($collections->pluck('affiliation_code')->all());

            foreach ($collections as $collection) {
                $affiliation = $affiliations[(string) $collection->affiliation_code] ?? null;
                self::closeActiveReviews($collection, $actor, 'Reemplazada por una nueva decisión.');

                if ($decision === CollectionDropoutReview::DECISION_CANCELLED) {
                    $adjustment = self::changeStatus($collection, CollectionAdjuster::STATUS_CANCELLED, 'Anulada por baja de la afiliación: '.$note, $actor, $batch);
                    self::recordReview($collection, $affiliation, $decision, CollectionAdjuster::STATUS_PENDING, CollectionAdjuster::STATUS_CANCELLED, $note, $actor, $batch, $adjustment);
                } else {
                    self::recordReview($collection, $affiliation, $decision, CollectionAdjuster::STATUS_PENDING, CollectionAdjuster::STATUS_PENDING, $note, $actor, $batch, null);
                }
            }

            $amount = round((float) $collections->sum('total_amount'), 2);

            SecurityAudit::log(
                $decision === CollectionDropoutReview::DECISION_CANCELLED ? 'AUDIT_DROPPED_COLLECTIONS_CANCELLED' : 'AUDIT_DROPPED_COLLECTIONS_KEPT',
                'administration.dropped-collections.'.($decision === CollectionDropoutReview::DECISION_CANCELLED ? 'cancel' : 'keep'),
                [
                    'panel' => 'administration',
                    'batch_uuid' => $batch,
                    'collection_ids' => $ids,
                    'amount' => $amount,
                    'note' => $note,
                ],
                $actor,
            );

            return ['count' => $collections->count(), 'amount' => $amount, 'batch_uuid' => $batch];
        });
    }

    /**
     * Una anulación solo se revierte si la cuota sigue anulada y su último ajuste es el
     * de la propia anulación: si alguien la cambió después, restituirla pisaría eso.
     */
    private static function restoreBlocker(Collection $collection, CollectionDropoutReview $review): ?string
    {
        if (Str::upper(trim((string) $collection->status)) !== CollectionAdjuster::STATUS_CANCELLED) {
            return 'ya no está anulada';
        }

        $lastAdjustmentId = CollectionAdjustment::query()->where('collection_id', $collection->id)->max('id');

        if ($review->adjustment_id !== null && (int) $lastAdjustmentId !== (int) $review->adjustment_id) {
            return 'tuvo otro ajuste después de anularse; revísela en Gestión de Cobranza';
        }

        return null;
    }

    private static function changeStatus(Collection $collection, string $status, string $reason, ?User $actor, string $batch): CollectionAdjustment
    {
        $before = (string) $collection->status;
        $collection->status = $status;
        $collection->save();

        return CollectionAdjustment::query()->create([
            'collection_id' => $collection->getKey(),
            'affiliation_code' => $collection->affiliation_code,
            'collection_invoice_number' => $collection->collection_invoice_number,
            'batch_uuid' => $batch,
            'mode' => CollectionAdjustment::MODE_DROPOUT,
            'changes' => ['status' => ['before' => $before, 'after' => $status]],
            'reason' => Str::limit($reason, 2000, ''),
            'performed_by_id' => $actor?->getKey(),
            'performed_by_name' => $actor?->name ?? self::SYSTEM_ACTOR,
            'ip' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
        ]);
    }

    private static function recordReview(
        Collection $collection,
        ?Model $affiliation,
        string $decision,
        string $statusBefore,
        string $statusAfter,
        string $note,
        ?User $actor,
        string $batch,
        ?CollectionAdjustment $adjustment,
        ?string $affiliationStatus = null,
    ): CollectionDropoutReview {
        return CollectionDropoutReview::query()->create([
            'collection_id' => $collection->getKey(),
            'affiliation_code' => $collection->affiliation_code,
            'affiliation_type' => $affiliation instanceof AffiliationCorporate || ($affiliation === null && CollectionReceivableReport::isCorporate($collection))
                ? self::TYPE_CORPORATE
                : self::TYPE_INDIVIDUAL,
            'affiliation_status' => $affiliationStatus ?? ($affiliation === null
                ? Str::upper(trim((string) $collection->affiliate_status))
                : Str::upper(trim((string) $affiliation->getAttribute('status')))),
            'decision' => $decision,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'amount' => $collection->total_amount,
            'due_date' => CollectionDueDate::of($collection)?->toDateString(),
            'batch_uuid' => $batch,
            'adjustment_id' => $adjustment?->getKey(),
            'note' => $note,
            'decided_by_id' => $actor?->getKey(),
            'decided_by_name' => $actor?->name ?? self::SYSTEM_ACTOR,
        ]);
    }

    private static function closeActiveReviews(Collection $collection, ?User $actor, string $note): void
    {
        CollectionDropoutReview::query()
            ->active()
            ->where('collection_id', $collection->getKey())
            ->get()
            ->each(fn (CollectionDropoutReview $review) => self::closeReview($review, $actor, $note));
    }

    private static function closeReview(CollectionDropoutReview $review, ?User $actor, string $note): void
    {
        $review->forceFill([
            'reverted_at' => now(),
            'reverted_by_id' => $actor?->getKey(),
            'reverted_by_name' => $actor?->name ?? self::SYSTEM_ACTOR,
            'revert_note' => Str::limit($note, 2000, ''),
        ])->save();
    }

    /**
     * @param  list<mixed>  $codes
     * @return array<string, Model>
     */
    private static function affiliationsFor(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(static fn (mixed $code): string => (string) $code, $codes))));
        $found = [];

        foreach (AffiliationCorporate::query()->whereIn('code', $codes)->get(['id', 'code', 'status']) as $corporate) {
            $found[(string) $corporate->code] = $corporate;
        }

        foreach (Affiliation::query()->whereIn('code', $codes)->get(['id', 'code', 'status']) as $individual) {
            $found[(string) $individual->code] = $individual;
        }

        return $found;
    }

    private static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private static function assertCan(?User $actor): void
    {
        if (! self::userCan($actor)) {
            throw new InvalidArgumentException('No tiene permiso para gestionar cuotas de afiliaciones dadas de baja.');
        }
    }

    private static function validNote(mixed $note): string
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', (string) ($note ?? '')));

        if (mb_strlen($note) < self::NOTE_MIN_LENGTH) {
            throw new InvalidArgumentException('Explique la decisión (mínimo '.self::NOTE_MIN_LENGTH.' caracteres).');
        }

        return Str::limit($note, 2000, '');
    }

    /**
     * @param  list<mixed>  $collectionIds
     * @return list<int>
     */
    private static function validIds(array $collectionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $collectionIds), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            throw new InvalidArgumentException('Seleccione al menos una cuota.');
        }

        if (count($ids) > self::MAX_BATCH) {
            throw new InvalidArgumentException('Puede procesar hasta '.self::MAX_BATCH.' cuotas por vez. Seleccionó '.count($ids).'.');
        }

        return $ids;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Collection>  $collections
     * @param  list<int>  $ids
     */
    private static function assertAllExist(\Illuminate\Database\Eloquent\Collection $collections, array $ids): void
    {
        if ($collections->count() !== count($ids)) {
            throw new InvalidArgumentException('Alguna de las cuotas seleccionadas ya no existe. Recargue la tabla e intente de nuevo.');
        }
    }

    private static function invoiceLabel(Collection $collection): string
    {
        return (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());
    }
}

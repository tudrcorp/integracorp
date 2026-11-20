<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Anula una cotización pendiente de un servicio médico para que el analista pueda
 * volver a cotizar.
 *
 * Al anular, los ítems que esa cotización había puesto EN GESTION vuelven a PENDIENTE,
 * la cuenta por cobrar deja de apuntar a ella y, si al servicio no le queda nada en
 * curso, el servicio vuelve a PENDIENTE para gestionarse de nuevo. La cotización no se
 * borra: queda ANULADA con su motivo, quién y cuándo.
 *
 * Solo se anulan cotizaciones pendientes y sin orden de servicio: una aprobada con
 * orden implicaría deshacer la orden y la factura, y eso tiene su propio flujo.
 */
final class CoordinationServiceQuoteAnnulment
{
    public const MINIMUM_REASON_LENGTH = 10;

    public const MAXIMUM_REASON_LENGTH = 1000;

    public const TRACE_ACTION = 'ANNUL_QUOTE';

    /** @var list<string> */
    private const REQUIRED_COLUMNS = ['annulled_at', 'annulled_by_user_id', 'annulled_by_name', 'annulment_reason'];

    /** @var list<string> */
    private const ITEM_TYPES = ['medication', 'lab', 'study', 'specialty'];

    private static ?bool $columnsReady = null;

    /**
     * La anulación necesita las columnas de la migración
     * `add_annulment_columns_to_operation_quote_generators_table`. Mientras no se
     * aplique, la opción no se ofrece en lugar de fallar al guardar.
     */
    public static function isAvailable(): bool
    {
        if (self::$columnsReady !== null) {
            return self::$columnsReady;
        }

        try {
            return self::$columnsReady = Schema::hasColumns(
                (new OperationQuoteGenerator)->getTable(),
                self::REQUIRED_COLUMNS,
            );
        } catch (Throwable) {
            return self::$columnsReady = false;
        }
    }

    public static function reasonValidationMessage(?string $reason): ?string
    {
        $length = mb_strlen(trim((string) $reason));

        if ($length === 0) {
            return 'Indique el motivo de la anulación.';
        }

        if ($length < self::MINIMUM_REASON_LENGTH) {
            return 'El motivo de la anulación debe tener al menos '.self::MINIMUM_REASON_LENGTH.' caracteres.';
        }

        if ($length > self::MAXIMUM_REASON_LENGTH) {
            return 'El motivo de la anulación no puede superar los '.self::MAXIMUM_REASON_LENGTH.' caracteres.';
        }

        return null;
    }

    /**
     * Debe ejecutarse dentro de una transacción: si algo falla, no queda nada a medias.
     *
     * @return array{quote_id: int, items_reverted: int, coordination_reopened: bool, is_courtesy: bool}
     *
     * @throws QuoteAnnulmentException
     */
    public static function annul(OperationCoordinationService $record, OperationQuoteGenerator $quote, string $reason): array
    {
        if (! self::isAvailable()) {
            throw new QuoteAnnulmentException('La anulación de cotizaciones aún no está habilitada en el sistema (falta aplicar su migración). Avise a soporte.');
        }

        $reason = trim($reason);
        $validationMessage = self::reasonValidationMessage($reason);

        if ($validationMessage !== null) {
            throw new QuoteAnnulmentException('Cotización '.ServiceOrderFlowTrace::quoteLabel($quote).': '.$validationMessage);
        }

        $lockedQuote = OperationQuoteGenerator::query()
            ->whereKey($quote->getKey())
            ->where('operation_coordination_service_id', $record->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedQuote instanceof OperationQuoteGenerator) {
            throw new QuoteAnnulmentException('La cotización '.ServiceOrderFlowTrace::quoteLabel($quote).' ya no pertenece a este servicio.');
        }

        if (! CoordinationServiceQuoteManager::isQuotePendingForApproval($lockedQuote)) {
            throw new QuoteAnnulmentException(
                'La cotización '.ServiceOrderFlowTrace::quoteLabel($lockedQuote).' está '.((string) $lockedQuote->status ?: 'sin estatus')
                .(filled($lockedQuote->operation_service_order_id) ? ' y tiene orden de servicio' : '')
                .': solo se anulan cotizaciones pendientes por aprobar y sin orden. Otro analista pudo haberla gestionado; recargue la página.'
            );
        }

        $user = Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';

        ServiceOrderFlowTrace::withContext(
            ['action' => self::TRACE_ACTION, 'reason' => $reason, 'quote_id' => (int) $lockedQuote->getKey()],
            function () use ($lockedQuote, $reason, $user, $userName): void {
                $lockedQuote->status = OperationQuoteGenerator::STATUS_ANNULLED;
                $lockedQuote->is_cash = false;
                $lockedQuote->annulled_at = now();
                $lockedQuote->annulled_by_user_id = $user?->getAuthIdentifier();
                $lockedQuote->annulled_by_name = $userName;
                $lockedQuote->annulment_reason = $reason;
                $lockedQuote->updated_by = $userName;
                $lockedQuote->save();
            },
        );

        $coordination = OperationCoordinationService::query()->whereKey($record->getKey())->lockForUpdate()->first() ?? $record;
        $liveQuotes = self::liveQuotesExcept($coordination, (int) $lockedQuote->getKey());

        $revertedLabels = self::revertQuoteItems($coordination, $lockedQuote, $liveQuotes);
        $coordinationReopened = self::reopenCoordinationIfIdle($coordination, $liveQuotes);

        self::refreshCoordinationQuotePricing($coordination, $liveQuotes, $userName);
        self::releaseReceivable($coordination, $lockedQuote, $liveQuotes, $userName);
        self::writeBitacora($coordination, $lockedQuote, $reason, $revertedLabels, $coordinationReopened, $userName);

        return [
            'quote_id' => (int) $lockedQuote->getKey(),
            'items_reverted' => count($revertedLabels),
            'coordination_reopened' => $coordinationReopened,
            'is_courtesy' => (bool) $lockedQuote->is_courtesy,
        ];
    }

    /**
     * Cotizaciones del servicio que siguen vivas: pendientes, o aprobadas que aún no
     * generaron su orden. Sus ítems no deben liberarse.
     *
     * @return Collection<int, OperationQuoteGenerator>
     */
    private static function liveQuotesExcept(OperationCoordinationService $coordination, int $excludedQuoteId): Collection
    {
        return OperationQuoteGenerator::query()
            ->where('operation_coordination_service_id', $coordination->getKey())
            ->whereKeyNot($excludedQuoteId)
            ->where(fn ($query) => $query
                ->where('status', OperationQuoteGenerator::STATUS_PENDING)
                ->orWhere(fn ($approved) => $approved
                    ->where('status', OperationQuoteGenerator::STATUS_APPROVED)
                    ->whereNull('operation_service_order_id')))
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Devuelve a PENDIENTE los ítems de la cotización que están EN GESTION, salvo los
     * que otra cotización viva o una orden activa siguen gestionando.
     *
     * @param  Collection<int, OperationQuoteGenerator>  $liveQuotes
     * @return list<string> Nombres de los ítems liberados.
     */
    private static function revertQuoteItems(OperationCoordinationService $coordination, OperationQuoteGenerator $quote, Collection $liveQuotes): array
    {
        $keysInLiveQuotes = $liveQuotes
            ->flatMap(fn (OperationQuoteGenerator $liveQuote): array => self::itemKeys($liveQuote))
            ->unique()
            ->all();

        $reverted = [];

        foreach (is_array($quote->items) ? $quote->items : [] as $line) {
            if (! is_array($line)) {
                continue;
            }

            $key = trim((string) ($line['key'] ?? ''));

            if ($key === '' || ! str_contains($key, ':') || in_array($key, $keysInLiveQuotes, true)) {
                continue;
            }

            [$type, $id] = explode(':', $key, 2);
            $modelClass = CoordinationServiceItemExternalManagement::clinicalItemModelClass($type);

            if ($modelClass === null || (int) $id <= 0) {
                continue;
            }

            /** @var Model|null $item */
            $item = $modelClass::query()
                ->where('operation_coordination_service_id', $coordination->getKey())
                ->whereKey((int) $id)
                ->lockForUpdate()
                ->first();

            if ($item === null || mb_strtoupper(trim((string) ($item->getAttribute('status') ?? ''))) !== 'EN GESTION') {
                continue;
            }

            if (CoordinationServiceItemFinalizationReversal::orderContainingItem($coordination, $type, $item, closedOnly: false) instanceof OperationServiceOrder) {
                continue;
            }

            $item->update(['status' => 'PENDIENTE']);
            $reverted[] = trim((string) ($line['label'] ?? $key));
        }

        return $reverted;
    }

    /**
     * El servicio vuelve a PENDIENTE solo si estaba EN GESTION y ya no le queda nada en
     * curso: ningún ítem EN GESTION, ninguna orden activa y ninguna cotización viva.
     *
     * @param  Collection<int, OperationQuoteGenerator>  $liveQuotes
     */
    private static function reopenCoordinationIfIdle(OperationCoordinationService $coordination, Collection $liveQuotes): bool
    {
        if (mb_strtoupper(trim((string) $coordination->status)) !== 'EN GESTION' || $liveQuotes->isNotEmpty()) {
            return false;
        }

        $hasActiveOrder = OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $coordination->getKey())
            ->whereNotIn('status', OperationServiceOrderValidity::closedStatuses())
            ->exists();

        if ($hasActiveOrder) {
            return false;
        }

        foreach (self::ITEM_TYPES as $type) {
            $modelClass = CoordinationServiceItemExternalManagement::clinicalItemModelClass($type);

            if ($modelClass === null) {
                continue;
            }

            $hasItemInProgress = $modelClass::query()
                ->where('operation_coordination_service_id', $coordination->getKey())
                ->whereRaw('UPPER(TRIM(status)) = ?', ['EN GESTION'])
                ->exists();

            if ($hasItemInProgress) {
                return false;
            }
        }

        $coordination->status = 'PENDIENTE';

        return true;
    }

    /**
     * Los montos de cotización del servicio pasan a la cotización viva más reciente, o
     * quedan vacíos si no hay ninguna: no deben seguir mostrando el precio anulado.
     *
     * @param  Collection<int, OperationQuoteGenerator>  $liveQuotes
     */
    private static function refreshCoordinationQuotePricing(OperationCoordinationService $coordination, Collection $liveQuotes, string $userName): void
    {
        $latest = $liveQuotes->first();

        $coordination->neto = $latest?->subtotal;
        $coordination->porcen_tdec = $latest?->porcentaje_ganancia;
        $coordination->quote_price = $latest?->total;
        $coordination->updated_by = $userName;
        $coordination->save();
    }

    /**
     * La cuenta por cobrar del servicio (casos reasignados a TDG) apuntaba a la
     * cotización anulada: pasa a la cotización viva más reciente o vuelve a quedar
     * pendiente de gestión.
     *
     * @param  Collection<int, OperationQuoteGenerator>  $liveQuotes
     */
    private static function releaseReceivable(OperationCoordinationService $coordination, OperationQuoteGenerator $quote, Collection $liveQuotes, string $userName): void
    {
        $receivables = OperationAccountsReceivable::query()
            ->where('operation_coordination_service_id', $coordination->getKey())
            ->where('operation_quote_generator_id', $quote->getKey())
            ->whereNull('operation_service_order_id')
            ->where('status', OperationAccountsReceivable::STATUS_QUOTE_ASSIGNED)
            ->lockForUpdate()
            ->get();

        foreach ($receivables as $receivable) {
            $replacement = $liveQuotes->first(
                fn (OperationQuoteGenerator $liveQuote): bool => $liveQuote->status === OperationQuoteGenerator::STATUS_PENDING
            );

            if ($replacement instanceof OperationQuoteGenerator) {
                AccountsReceivableManager::syncFromQuote($replacement);

                continue;
            }

            $receivable->update([
                'operation_quote_generator_id' => null,
                'quote_number' => null,
                'quote_amount_usd' => null,
                'quote_amount_ves' => null,
                'bcv_rate' => null,
                'is_courtesy' => false,
                'status' => OperationAccountsReceivable::STATUS_PENDING_TDG,
                'updated_by' => $userName,
            ]);

            ServiceOrderFlowTrace::record(
                ServiceOrderFlowTrace::RECEIVABLE_REOPENED,
                sprintf(
                    'La cuenta por cobrar %s quedó pendiente de gestión porque se anuló la cotización %s.',
                    AccountsReceivableManager::formatReceivableNumber((int) $receivable->getKey()),
                    ServiceOrderFlowTrace::quoteLabel($quote),
                ),
                ['quote' => $quote, 'coordination' => $coordination],
            );
        }
    }

    /**
     * @param  list<string>  $revertedLabels
     */
    private static function writeBitacora(
        OperationCoordinationService $coordination,
        OperationQuoteGenerator $quote,
        string $reason,
        array $revertedLabels,
        bool $coordinationReopened,
        string $userName,
    ): void {
        $description = 'COTIZACIÓN ANULADA'."\n"
            .'Cotización: '.ServiceOrderFlowTrace::quoteLabel($quote).' ('.((string) $quote->type_service ?: 'sin tipo').')'."\n"
            .'Analista: '.$userName."\n"
            .'Motivo: '.$reason
            .($revertedLabels !== [] ? "\n".'Ítems devueltos a PENDIENTE: '.implode(', ', $revertedLabels) : '')
            .($coordinationReopened ? "\n".'El servicio volvió a PENDIENTE para gestionarse de nuevo.' : '');

        if (filled($coordination->telemedicine_case_id)) {
            ObservationCase::query()->create([
                'telemedicine_case_id' => $coordination->telemedicine_case_id,
                'description' => $description,
                'created_by' => Auth::id() !== null ? (string) Auth::id() : $userName,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private static function itemKeys(OperationQuoteGenerator $quote): array
    {
        return collect(is_array($quote->items) ? $quote->items : [])
            ->map(fn (mixed $line): string => is_array($line) ? trim((string) ($line['key'] ?? '')) : '')
            ->filter()
            ->values()
            ->all();
    }
}
